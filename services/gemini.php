<?php
/**
 * Serviço de Integração com Google Gemini API
 * MapsProspector Pro
 */

class GeminiService {
    private $apiKey;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    
    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        if (empty($this->apiKey) || $this->apiKey === 'SUA_CHAVE_AQUI') {
            throw new Exception("Chave de API do Gemini não configurada. Configure em config/config.php");
        }
    }
    
    /**
     * Busca leads no Google Maps usando Gemini
     */
    public function searchLeadsOnMaps(
        $query,
        $location = null,
        $excludeNames = [],
        $modelName = 'gemini-2.0-flash',
        $coords = null,
        $locationName = null
    ) {
        // Normaliza localização
        $normalizedLocation = $location && !stripos($location, 'brasil') 
            ? "$location, Brasil" 
            : $location;
        
        // Contexto de localização
        $locationContext = "";
        if ($locationName) {
            $locationContext = "em toda a cidade de $locationName (Brasil)";
        } elseif ($coords) {
            $locationContext = "num raio amplo próximo às coordenadas Lat {$coords['latitude']}, Long {$coords['longitude']}";
        } else {
            $locationContext = "na cidade/região de $normalizedLocation";
        }
        
        // Prompt otimizado
        $prompt = "Atue como um Robô de Extração de Dados em LARGA ESCALA (Massive Scraper).

MISSÃO CRÍTICA: Gerar uma lista EXAUSTIVA de TODAS as empresas do ramo \"$query\" localizadas $locationContext.

OBJETIVO DE VOLUME:
- NÃO se limite a 10 ou 20 resultados.
- SUA META É ENCONTRAR ENTRE 50 A 100+ EMPRESAS se existirem na região.
- Varra diretórios, listas de \"Melhores da Região\", GuiaMais, Solutudo, Redes Sociais e Mapas.
- Liste TUDO o que encontrar.

FLUXO DE EXECUÇÃO OBRIGATÓRIO:
1. BUSCA MASSIVA (Google Search): Encontre o maior número possível de nomes de estabelecimentos.
2. ENRIQUECIMENTO DE DADOS (Google Maps & Web):
   - Para CADA empresa da lista gigante, busque os detalhes.
   - Se o telefone não estiver na lista original, use a ferramenta Google Maps para pegar o oficial.

CRITÉRIOS DE DADOS RICOS (PREENCHA O MÁXIMO POSSÍVEL):
- SÓCIOS/RESPONSÁVEIS: Procure nomes de donos/sócios (LinkedIn, CNPJ.biz, Sites Institucionais).
- CNPJ: Tente identificar o CNPJ.
- EMAIL: Procure emails de contato.
- TELEFONE: Obrigatório (Priorize Celular/WhatsApp).
- MAPS URI: Link da ficha do Google.

IGNORE (JÁ LISTADOS): " . (empty($excludeNames) ? 'nenhuma' : implode(', ', $excludeNames)) . ".

SAÍDA OBRIGATÓRIA (JSON ARRAY ÚNICO):
[
  {
    \"name\": \"Nome da Empresa\",
    \"address\": \"Endereço Completo\",
    \"phone\": \"Telefone\",
    \"email\": \"Email encontrado ou vazio\",
    \"partners\": \"Nome dos Sócios/Donos ou Vazio\",
    \"cnpj\": \"CNPJ encontrado ou Vazio\",
    \"website\": \"Site/Instagram\",
    \"mapsUri\": \"Link\"
  }
]

IMPORTANTE: Retorne um JSON válido contendo TODOS os resultados encontrados. Não trunque a lista.";
        
        // Configuração da requisição
        $url = "{$this->baseUrl}/models/{$modelName}:generateContent?key={$this->apiKey}";
        
        $tools = [['googleSearch' => new stdClass()]];
        $tools[] = ['googleMaps' => new stdClass()];
        
        $config = [
            'tools' => $tools,
            'maxOutputTokens' => 8192
        ];
        
        // Adiciona coordenadas se disponíveis
        if ($coords) {
            $config['toolConfig'] = [
                'retrievalConfig' => [
                    'latLng' => [
                        'latitude' => floatval($coords['latitude']),
                        'longitude' => floatval($coords['longitude'])
                    ]
                ]
            ];
        }
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192
            ],
            'tools' => $tools
        ];
        
        if (isset($config['toolConfig'])) {
            $payload['toolConfig'] = $config['toolConfig'];
        }
        
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 120
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("Erro na requisição: $error");
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? "Erro HTTP $httpCode";
                
                if ($httpCode === 429) {
                    throw new Exception("Muitas requisições. Aguarde 1 minuto.");
                }
                
                throw new Exception("Erro na API: $errorMsg");
            }
            
            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            // Extrai JSON da resposta
            $leads = $this->extractJson($text);
            
            // Se não conseguiu extrair JSON, tenta usar chunks
            if (empty($leads)) {
                $leads = $this->extractFromChunks($data);
            }
            
            // Formata os leads
            return $this->formatLeads($leads, $locationName ?: $location);
            
        } catch (Exception $e) {
            error_log("Erro Gemini: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Extrai JSON da resposta do Gemini
     */
    private function extractJson($text) {
        if (empty($text)) return [];
        
        // Remove caracteres de controle
        $text = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $text);
        
        // Remove markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $text = trim($matches[1]);
        }
        
        // Tenta encontrar array JSON
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        
        if ($start !== false && $end !== false) {
            $jsonStr = substr($text, $start, $end - $start + 1);
            $parsed = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        }
        
        return [];
    }
    
    /**
     * Extrai leads dos chunks (fallback)
     */
    private function extractFromChunks($data) {
        $chunks = $data['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
        $leads = [];
        
        foreach ($chunks as $chunk) {
            if (isset($chunk['web']) || isset($chunk['maps'])) {
                $leads[] = [
                    'name' => $chunk['web']['title'] ?? $chunk['maps']['title'] ?? 'Resultado Encontrado',
                    'address' => $chunk['maps']['address'] ?? 'Verificar link',
                    'mapsUri' => $chunk['maps']['uri'] ?? $chunk['web']['uri'] ?? '',
                    'phone' => '',
                    'email' => '',
                    'partners' => '',
                    'cnpj' => '',
                    'website' => $chunk['web']['uri'] ?? $chunk['maps']['uri'] ?? ''
                ];
            }
        }
        
        return $leads;
    }
    
    /**
     * Formata leads para o formato esperado
     */
    private function formatLeads($leads, $location) {
        $formatted = [];
        
        foreach ($leads as $idx => $lead) {
            // Formata telefone
            $phone = $lead['phone'] ?? '';
            if ($phone) {
                $phone = preg_replace('/[^\d+]/', '', $phone);
                if (preg_match('/^55(\d{10,11})$/', $phone, $m)) {
                    $phone = $m[1];
                }
                if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phone, $m)) {
                    $phone = "({$m[1]}) {$m[2]}-{$m[3]}";
                }
            }
            
            // Gera mapsUri se não existir
            $mapsUri = $lead['mapsUri'] ?? '';
            if (empty($mapsUri)) {
                $searchQuery = urlencode(($lead['name'] ?? '') . ' ' . $location);
                $mapsUri = "https://www.google.com/maps/search/?api=1&query=$searchQuery";
            }
            
            $formatted[] = [
                'id' => 'lead-' . time() . '-' . $idx,
                'name' => $lead['name'] ?? '',
                'address' => $lead['address'] ?? '',
                'phone' => $phone,
                'email' => $lead['email'] ?? '',
                'website' => $lead['website'] ?? '',
                'mapsUri' => $mapsUri,
                'cnpj' => $lead['cnpj'] ?? '',
                'partners' => $lead['partners'] ?? '',
                'tag' => $lead['tag'] ?? '',
                'latitude' => isset($lead['latitude']) ? floatval($lead['latitude']) : null,
                'longitude' => isset($lead['longitude']) ? floatval($lead['longitude']) : null
            ];
        }
        
        return $formatted;
    }
}
