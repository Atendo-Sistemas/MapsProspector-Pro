<?php
/**
 * Serviço de Integração com API de IA para Busca (OpenRouter.ai)
 * MapsProspector Pro - Método alternativo de busca
 */

class IASearchService {
    private $apiKey;
    private $models;
    private $currentModelIndex = 0;

    private const BASE_URL = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct($apiKey = null, $model = null) {
        if ($apiKey !== null && !empty(trim((string)$apiKey))) {
            $this->apiKey = trim($apiKey);
        } else {
            $this->apiKey = defined('OPENROUTER_API_KEY') ? trim((string)OPENROUTER_API_KEY) : '';
        }

        if ($model !== null) {
            $this->models = is_array($model) ? $model : [$model];
        } else {
            $defaultModel = defined('OPENROUTER_DEFAULT_MODEL') ? OPENROUTER_DEFAULT_MODEL : 'google/gemini-2.0-flash-001';
            $fallbackModel = defined('OPENROUTER_FALLBACK_MODEL') ? OPENROUTER_FALLBACK_MODEL : '';
            $this->models = $fallbackModel ? [$defaultModel, $fallbackModel] : [$defaultModel];
        }

        if (empty($this->apiKey) || $this->apiKey === 'PLACEHOLDER_API_KEY') {
            throw new Exception("Chave de API de IA não configurada. Configure a OPENROUTER_API_KEY no arquivo .env ou nas configurações da plataforma.");
        }
    }

    private function getCurrentModel() {
        return $this->models[$this->currentModelIndex] ?? $this->models[0];
    }

    private function hasNextModel() {
        return $this->currentModelIndex < count($this->models) - 1;
    }

    private function switchToNextModel() {
        $this->currentModelIndex++;
        error_log("IASearchService: Tentando modelo fallback: " . $this->getCurrentModel());
    }

    /**
     * Faz uma requisição POST à API do OpenRouter.
     */
    private function fetchOpenRouter(array $messages) {
        $url = self::BASE_URL;
        
        $currentModel = $this->getCurrentModel();
        
        $body = [
            'model' => $currentModel,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 8192,
        ];

        if (stripos($currentModel, 'minimax') !== false) {
            $body['reasoning'] = true;
        }

        $jsonBody = json_encode($body);
        
        $siteUrl = 'https://mapsprospector.com';
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
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
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . $siteUrl,
                'X-OpenRouter-Title: MapsProspector Pro',
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
            if ($httpCode === 401) throw new Exception("Chave de API de IA inválida. Verifique a configuração.");
            throw new Exception("Erro na API de IA: $errorMsg");
        }
        
        $trimmed = trim($response);
        if (empty($trimmed)) {
            throw new Exception("Resposta vazia da API de IA");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Resposta inválida da API de IA (não é JSON válido): " . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Busca leads no Google Maps usando IA (OpenRouter).
     *
     * @param string $query Termo de busca
     * @param string|null $location Localização (cidade)
     * @param int $maxResults Número máximo de resultados desejados
     * @return array ['leads' => array, 'pages_used' => int]
     */
    public function searchLeadsOnMaps(
        $query,
        $location = null,
        $maxResults = 20
    ) {
        try {
            $searchQuery = trim($query);
            $locationText = $location ?? 'Brasil';
            
            $normalizedLocation = trim($locationText);
            if (!stripos($normalizedLocation, 'brasil') && !stripos($normalizedLocation, 'brazil')) {
                $normalizedLocation .= ', Brasil';
            }

            $allLeads = [];
            $resultsPerRequest = 20;
            $maxResults = max(1, (int)$maxResults);
            $totalRequests = (int) ceil($maxResults / $resultsPerRequest);
            $totalRequests = min($totalRequests, 5);
            
            $resultsSoFar = 0;
            $rateLimitHit = false;
            
            for ($attempt = 0; $attempt < $totalRequests; $attempt++) {
                $currentLimit = min($resultsPerRequest, $maxResults - $resultsSoFar);
                if ($currentLimit <= 0) break;
                
                $prompt = "Busque empresas do tipo \"$searchQuery\" em \"$normalizedLocation\". 
Retorne uma lista de empresas com os seguintes campos para cada resultado:
- nome (name)
- endereço completo (address)  
- telefone (phone)
- website (opcional)
- avaliação (rating de 1 a 5, se disponível)
- número de avaliações (reviews count)

Busque exatamente $currentLimit resultados diferentes. NÃO repita empresas já mostradas anteriormente.
Retorne os dados em formato JSON array, ex: [{\"name\": \"...\", \"address\": \"...\", \"phone\": \"...\", \"website\": \"...\", \"rating\": 4.5, \"reviews\": 100}]";

                $messages = [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ];

                $leads = [];
                $modelFailed = false;
                $lastError = null;
                
                $this->currentModelIndex = 0;
                
                while ($this->currentModelIndex < count($this->models)) {
                    try {
                        $data = $this->fetchOpenRouter($messages);
                        $leads = $this->parseOpenRouterResponse($data, $location);
                        break;
                    } catch (Exception $e) {
                        $lastError = $e;
                        $modelFailed = true;
                        
                        if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'Muitas requisições') !== false) {
                            throw $e;
                        }
                        
                        if (!$this->hasNextModel()) {
                            throw $e;
                        }
                        
                        $this->switchToNextModel();
                        $modelFailed = false;
                    }
                }
                
                if (!empty($leads)) {
                    $allLeads = array_merge($allLeads, $leads);
                    $resultsSoFar += count($leads);
                }
                
                if ($resultsSoFar >= $maxResults) break;
                
                usleep(500000);
            }

            if (empty($allLeads)) {
                throw new Exception("Nenhum resultado encontrado para \"$query\" em \"$normalizedLocation\".");
            }

            $allLeads = array_slice($allLeads, 0, $maxResults);
            $pagesUsed = (int) ceil(count($allLeads) / 20);
            return ['leads' => $allLeads, 'pages_used' => $pagesUsed];
            
        } catch (Exception $e) {
            error_log("Erro na busca por IA: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Converte a resposta do OpenRouter para o formato de leads do sistema.
     */
    private function parseOpenRouterResponse($data, $location) {
        $leads = [];
        
        if (!isset($data['choices']) || empty($data['choices'])) {
            error_log("OpenRouter API: estrutura de resposta não reconhecida");
            return [];
        }

        $choice = $data['choices'][0] ?? null;
        
        if (!isset($choice['message']['content'])) {
            return [];
        }

        $content = $choice['message']['content'];
        
        if (empty($content)) {
            return [];
        }

        $content = trim($content);
        
        if (preg_match('/\[.*\]/s', $content, $match)) {
            $jsonStr = $match[0];
            $parsed = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                foreach ($parsed as $item) {
                    $leads[] = $this->convertOpenRouterLead($item);
                }
                return $leads;
            }
        }

        if (preg_match('/\{.*\}/s', $content, $match)) {
            $jsonStr = $match[0];
            $parsed = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                if (isset($parsed['results']) && is_array($parsed['results'])) {
                    foreach ($parsed['results'] as $item) {
                        $leads[] = $this->convertOpenRouterLead($item);
                    }
                } elseif (isset($parsed['leads']) && is_array($parsed['leads'])) {
                    foreach ($parsed['leads'] as $item) {
                        $leads[] = $this->convertOpenRouterLead($item);
                    }
                }
                return $leads;
            }
        }

        $leads = $this->parseTextResponse($content, $location);

        return $leads;
    }

    /**
     * Converte um lead do formato OpenRouter para o formato do sistema.
     */
    private function convertOpenRouterLead($item) {
        $name = $item['name'] ?? $item['title'] ?? '';
        $address = $item['address'] ?? $item['location'] ?? '';
        $phone = $item['phone'] ?? $item['telephone'] ?? $item['contact'] ?? '';
        $website = $item['website'] ?? $item['url'] ?? '';
        $rating = isset($item['rating']) ? floatval($item['rating']) : (isset($item['stars']) ? floatval($item['stars']) : null);
        $reviews = isset($item['reviews']) ? intval($item['reviews']) : (isset($item['reviewCount']) ? intval($item['reviewCount']) : null);

        if (!empty($phone) && !preg_match('/[\(\)\-]/', $phone)) {
            $phoneClean = preg_replace('/[^\d+]/', '', $phone);
            if (preg_match('/^55(\d{10,11})$/', $phoneClean, $m)) {
                $phoneClean = $m[1];
            }
            if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phoneClean, $m)) {
                $phone = "({$m[1]}) {$m[2]}-{$m[3]}";
            }
        }

        return [
            'id' => 'lead-' . time() . '-' . rand(1000, 9999),
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'email' => '',
            'website' => $website,
            'mapsUri' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($name . ' ' . $address),
            'cnpj' => '',
            'partners' => '',
            'tag' => 'prospect_ia',
            'latitude' => null,
            'longitude' => null,
            'rating' => $rating,
            'reviews' => $reviews,
        ];
    }

    /**
     * Faz parse de resposta em texto quando a API não retorna dados estruturados.
     */
    private function parseTextResponse($text, $location) {
        $leads = [];
        
        $lines = preg_split('/\n/', $text);
        $currentLead = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^[\d\-\*•]\s*[\.\)]?\s*(.+)/i', $line, $m)) {
                if (!empty($currentLead) && isset($currentLead['name'])) {
                    $leads[] = $this->convertOpenRouterLead($currentLead);
                }
                $currentLead = ['name' => trim($m[1])];
            } elseif (preg_match('/endereço[:\s]+(.+)/i', $line, $m)) {
                $currentLead['address'] = trim($m[1]);
            } elseif (preg_match('/telefone[:\s]+(.+)/i', $line, $m) || preg_match('/fone[:\s]+(.+)/i', $line, $m)) {
                $currentLead['phone'] = trim($m[1]);
            } elseif (preg_match('/site[:\s]+(.+)/i', $line, $m) || preg_match('/website[:\s]+(.+)/i', $line, $m)) {
                $currentLead['website'] = trim($m[1]);
            } elseif (preg_match('/avaliação[:\s]+([\d,\.]+)/i', $line, $m) || preg_match('/estrelas[:\s]+([\d,\.]+)/i', $line, $m)) {
                $currentLead['rating'] = floatval(str_replace(',', '.', $m[1]));
            } elseif (preg_match('/avaliações[:\s]+(\d+)/i', $line, $m) || preg_match('/(\d+)\s*avaliaç/i', $line, $m)) {
                $currentLead['reviews'] = intval($m[1]);
            }
        }
        
        if (!empty($currentLead) && isset($currentLead['name'])) {
            $leads[] = $this->convertOpenRouterLead($currentLead);
        }

        return $leads;
    }
}
