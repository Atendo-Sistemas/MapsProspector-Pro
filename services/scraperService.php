<?php
/**
 * Serviço de Integração com API de Busca (Google Maps)
 * MapsProspector Pro
 */

class ScraperService {
    private $apiKey;
    private $baseUrl = 'https://api.apify.com/v2/acts/compass~crawler-google-places/run-sync-get-dataset-items';

    public function __construct($apiKey = null) {
        if ($apiKey !== null && !empty(trim((string)$apiKey))) {
            $this->apiKey = trim($apiKey);
        } else {
            $this->apiKey = defined('SCRAPER_API_KEY') ? trim((string)SCRAPER_API_KEY) : '';
        }

        if (empty($this->apiKey) || $this->apiKey === 'SUA_CHAVE_AQUI') {
            throw new Exception("Chave de API de Busca não configurada. Configure nas Configurações ou em config/config.php");
        }
    }

    /**
     * Faz uma única requisição POST à API de busca com body JSON.
     * Retorna o array decodificado da resposta ou null em caso de falha.
     */
    private function fetchApify(array $body) {
        $jsonBody = json_encode($body);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            throw new Exception("Erro na requisição: $error");
        }
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? $errorData['message'] ?? "Erro HTTP $httpCode";
            if ($httpCode === 429) throw new Exception("Muitas requisições. Aguarde 1 minuto.");
            if ($httpCode === 401) throw new Exception("Chave de API de busca inválida. Verifique a configuração.");
            throw new Exception("Erro na API: $errorMsg");
        }
        $trimmed = trim($response);
        if (empty($trimmed)) {
            throw new Exception("Resposta vazia da API");
        }
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Resposta inválida da API (não é JSON válido): " . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Busca leads no Google Maps.
     * Uma única chamada retorna até maxCrawledPlacesPerSearch resultados.
     * O sistema de tokens desconta 1 token por resultado único.
     *
     * @param string $query Termo de busca
     * @param string|null $location Localização (cidade)
     * @param array $excludeNames Nomes a excluir
     * @param string|null $modelName Não usado
     * @param array|null $coords Coordenadas GPS (opcional; não enviado no body padrão)
     * @param string|null $locationName Nome da localização GPS
     * @param int $maxResults Número máximo de resultados desejados (padrão: 1000)
     * @param int|null $maxTokensAvailable Máximo de tokens (páginas); null = usa limite padrão
     * @return array ['leads' => array, 'pages_used' => int]
     */
    public function searchLeadsOnMaps(
        $query,
        $location = null,
        $excludeNames = [],
        $modelName = null,
        $coords = null,
        $locationName = null,
        $maxResults = 1000,
        $maxTokensAvailable = null
    ) {
        try {
            $searchQuery = trim($query);
            if ($locationName) {
                $searchQuery .= ' em ' . trim($locationName);
            } elseif ($location) {
                $normalizedLocation = trim($location);
                if (!stripos($normalizedLocation, 'brasil') && !stripos($normalizedLocation, 'brazil')) {
                    $normalizedLocation .= ', Brasil';
                }
                $searchQuery .= ' em ' . $normalizedLocation;
            }

            $limite = $maxResults;
            if ($maxTokensAvailable !== null && $maxTokensAvailable > 0) {
                $limite = min($limite, (int)$maxTokensAvailable * 20);
            }
            $limite = max(1, min(1000, (int)$limite));

            $body = [
                'includeWebResults' => false,
                'language' => 'pt-BR',
                'maxCrawledPlacesPerSearch' => $limite,
                'maxImages' => 1,
                'maxQuestions' => 0,
                'maxReviews' => 0,
                'scrapeContacts' => false,
                'scrapeDirectories' => false,
                'scrapeImageAuthors' => false,
                'scrapePlaceDetailPage' => false,
                'scrapeReviewsPersonalData' => true,
                'scrapeTableReservationProvider' => false,
                'searchStringsArray' => [$searchQuery],
                'skipClosedPlaces' => false,
            ];

            $data = $this->fetchApify($body);

            $leads = $this->parseApifyResponse($data, $locationName ?: $location);

            if (!empty($excludeNames)) {
                $leads = array_values(array_filter($leads, function ($lead) use ($excludeNames) {
                    return !in_array(strtolower($lead['name']), array_map('strtolower', $excludeNames));
                }));
            }

            return ['leads' => $leads, 'results_count' => count($leads)];
        } catch (Exception $e) {
            error_log("Erro API de busca: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Converte a resposta da API de busca (dataset items) para o formato de leads do sistema.
     */
    private function parseApifyResponse($data, $location) {
        $leads = [];
        $results = [];

        if (isset($data['local_results']) && is_array($data['local_results'])) {
            $results = $data['local_results'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $results = $data['data'];
        } elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $results = $data;
        } else {
            error_log("API de busca: estrutura de resposta não reconhecida. Chaves: " . (is_array($data) ? json_encode(array_keys($data)) : gettype($data)));
            return [];
        }

        foreach ($results as $idx => $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = '';
            if (isset($item['title']) && is_string($item['title'])) {
                $name = trim($item['title']);
            } elseif (isset($item['name']) && is_string($item['name'])) {
                $name = trim($item['name']);
            }
            if (empty($name) || strlen($name) < 2 || is_numeric($name)) {
                continue;
            }

            $address = '';
            if (isset($item['address']) && is_string($item['address'])) {
                $address = trim($item['address']);
            } elseif (isset($item['full_address']) && is_string($item['full_address'])) {
                $address = trim($item['full_address']);
            }

            $phone = '';
            if (isset($item['phone']) && is_string($item['phone'])) {
                $phone = trim($item['phone']);
            } elseif (isset($item['phoneUnformatted']) && is_string($item['phoneUnformatted'])) {
                $phone = trim($item['phoneUnformatted']);
            }
            if ($phone && !preg_match('/[\(\)\-]/', $phone)) {
                $phoneClean = preg_replace('/[^\d+]/', '', $phone);
                if (preg_match('/^55(\d{10,11})$/', $phoneClean, $m)) {
                    $phoneClean = $m[1];
                }
                if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phoneClean, $m)) {
                    $phone = "({$m[1]}) {$m[2]}-{$m[3]}";
                }
            }

            $website = '';
            if (isset($item['website']) && is_string($item['website'])) {
                $website = trim($item['website']);
            } elseif (isset($item['url']) && is_string($item['url']) && preg_match('/^https?:\/\//', $item['url']) && strpos($item['url'], 'google.com/maps') === false) {
                $website = trim($item['url']);
            }

            $latitude = null;
            $longitude = null;
            if (isset($item['location']) && is_array($item['location'])) {
                $latitude = isset($item['location']['lat']) ? floatval($item['location']['lat']) : null;
                $longitude = isset($item['location']['lng']) ? floatval($item['location']['lng']) : null;
            }
            if ($latitude === null && isset($item['latitude'])) {
                $latitude = floatval($item['latitude']);
            }
            if ($longitude === null && isset($item['longitude'])) {
                $longitude = floatval($item['longitude']);
            }

            $mapsUri = '';
            if (isset($item['url']) && is_string($item['url']) && strpos($item['url'], 'google.com/maps') !== false) {
                $mapsUri = trim($item['url']);
            } elseif (isset($item['placeId']) && !empty($item['placeId'])) {
                $mapsUri = 'https://www.google.com/maps/place/?q=place_id:' . urlencode($item['placeId']);
            } elseif (isset($item['kgmid']) && !empty($item['kgmid'])) {
                $cid = ltrim($item['kgmid'], '/');
                $mapsUri = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($name . ' ' . $address);
            } else {
                $mapsUri = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($name . ' ' . $address);
            }

            $rating = isset($item['totalScore']) ? floatval($item['totalScore']) : (isset($item['rating']) ? floatval($item['rating']) : null);
            $reviews = isset($item['reviewsCount']) ? intval($item['reviewsCount']) : (isset($item['reviews']) ? intval($item['reviews']) : null);

            $lead = [
                'id' => 'lead-' . time() . '-' . $idx,
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'email' => '',
                'website' => $website,
                'mapsUri' => $mapsUri,
                'cnpj' => '',
                'partners' => '',
                'tag' => '',
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
            if ($rating !== null) {
                $lead['rating'] = $rating;
            }
            if ($reviews !== null) {
                $lead['reviews'] = $reviews;
            }
            $leads[] = $lead;
        }

        return $leads;
    }
}
