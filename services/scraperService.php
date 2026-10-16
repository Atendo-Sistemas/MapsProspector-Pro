<?php
/**
 * Serviço de Scraping com Loop de Verificação (Polling) para a Apify
 */

class ScraperService {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = trim($apiKey);
    }

    public function searchLeadsOnMaps($query, $location, $excludeNames = [], $folderId = null, $coords = null, $locationName = null, $maxPlaces = 200, $maxTokens = null) {
        if (empty($this->apiKey)) {
            throw new Exception("Chave da API Apify não configurada.");
        }

        $actorId = "AabCualFIriz3X6Fs"; 
        
        // 1. Inicia a execução do Actor (sem waitForFinish para controlar manualmente)
        $url = "https://api.apify.com/v2/acts/{$actorId}/runs?token=" . $this->apiKey;

        $input = [
            "searchStringsArray" => [$query],
            "maxCrawledPlacesPerSearch" => (int)$maxPlaces,
            "extractContactsFromWebsite" => true,
            "skipClosedPlaces" => false,
            "language" => "pt-BR"
        ];

        if ($coords && isset($coords['latitude'], $coords['longitude'])) {
            $input["customGeolocation"] = [
                "type" => "Point",
                "coordinates" => [(float)$coords['longitude'], (float)$coords['latitude']],
                "radiusKm" => 15
            ];
            if ($locationName) {
                $input["locationQueries"] = [$locationName];
            }
        } elseif ($location) {
            $input["locationQueries"] = [$location];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($input),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            throw new Exception("Erro ao iniciar execução na Apify ({$httpCode}): " . $response);
        }

        $runData = json_decode($response, true);
        $runId = $runData['data']['id'] ?? null;
        $defaultDatasetId = $runData['data']['defaultDatasetId'] ?? null;

        if (!$runId || !$defaultDatasetId) {
            throw new Exception("A Apify não retornou o ID da execução.");
        }

        // 2. Loop de Polling: Aguarda a Apify terminar (checar a cada 3 segundos, até 3 minutos)
        $status = 'READY';
        $maxTries = 60; // 60 tentativas * 3 segundos = 180 segundos (3 min)
        $tries = 0;

        while ($status !== 'SUCCEEDED' && $tries < $maxTries) {
            sleep(3); // Aguarda 3 segundos
            $tries++;

            $statusUrl = "https://api.apify.com/v2/actor-runs/{$runId}?token=" . $this->apiKey;
            $chStatus = curl_init($statusUrl);
            curl_setopt_array($chStatus, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15
            ]);
            $statusResp = curl_exec($chStatus);
            curl_close($chStatus);

            $statusData = json_decode($statusResp, true);
            $status = $statusData['data']['status'] ?? 'UNKNOWN';

            if ($status === 'FAILED' || $status === 'ABORTED' || $status === 'TIMED-OUT') {
                throw new Exception("A execução da Apify falhou com status: " . $status);
            }
        }

        if ($status !== 'SUCCEEDED') {
            throw new Exception("Tempo limite esgotado. A Apify demorou muito para concluir (Status atual: {$status}).");
        }

        // 3. Baixa os itens coletados no Dataset da Apify
        $itemsUrl = "https://api.apify.com/v2/datasets/{$defaultDatasetId}/items?token=" . $this->apiKey;
        
        $ch2 = curl_init($itemsUrl);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60
        ]);
        $itemsResponse = curl_exec($ch2);
        $itemsCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($itemsCode !== 200) {
            throw new Exception("Erro ao baixar os resultados do dataset da Apify. Código: " . $itemsCode);
        }

        $rawItems = json_decode($itemsResponse, true) ?? [];
        $leads = [];

        foreach ($rawItems as $item) {
            $name = trim($item['title'] ?? $item['name'] ?? '');
            if (empty($name)) continue;

            $phone = $item['phone'] ?? $item['phoneNumber'] ?? '';
            $email = $item['email'] ?? ($item['emails'][0] ?? '');
            $website = $item['website'] ?? $item['url'] ?? '';
            $address = $item['address'] ?? '';
            $mapsUri = $item['url'] ?? $item['googleMapsUrl'] ?? '';
            $lat = $item['location']['lat'] ?? $item['latitude'] ?? null;
            $lon = $item['location']['lng'] ?? $item['longitude'] ?? null;

            if (!empty($excludeNames) && in_array($name, $excludeNames)) {
                continue;
            }

            $leads[] = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'website' => $website,
                'address' => $address,
                'mapsUri' => $mapsUri,
                'cnpj' => '',
                'partners' => '',
                'latitude' => $lat,
                'longitude' => $lon
            ];
        }

        return [
            'leads' => $leads,
            'pages_used' => count($leads)
        ];
    }
}