<?php
/**
 * Serviço de Integração com ScraperAPI (Thordata)
 * MapsProspector Pro
 */

class ScraperService {
    private $apiKey;
    private $baseUrl = 'https://scraperapi.thordata.com/request';
    
    public function __construct($apiKey = null) {
        // Prioriza a chave passada como parâmetro (do banco de dados)
        // Se não fornecida, tenta usar a constante do config.php como fallback
        if ($apiKey !== null && !empty($apiKey)) {
            $this->apiKey = $apiKey;
        } else {
            $this->apiKey = defined('SCRAPER_API_KEY') ? SCRAPER_API_KEY : '';
        }
        
        if (empty($this->apiKey) || $this->apiKey === 'SUA_CHAVE_AQUI') {
            throw new Exception("Chave de API do Scraper não configurada. Configure nas Configurações ou em config/config.php");
        }
    }
    
    /**
     * Busca leads no Google Maps usando ScraperAPI
     * 
     * @param string $query Termo de busca
     * @param string|null $location Localização (cidade)
     * @param array $excludeNames Nomes a excluir
     * @param string|null $modelName Não usado, mantido para compatibilidade
     * @param array|null $coords Coordenadas GPS ['latitude' => x, 'longitude' => y]
     * @param string|null $locationName Nome da localização GPS
     * @param int $maxResults Número máximo de resultados desejados (padrão: 100)
     * @return array Array de leads encontrados
     */
    public function searchLeadsOnMaps(
        $query,
        $location = null,
        $excludeNames = [],
        $modelName = null, // Não usado nesta API, mantido para compatibilidade
        $coords = null,
        $locationName = null,
        $maxResults = 100
    ) {
        try {
            // Monta a query de busca (formato simples: "query location" sem "em")
            $searchQuery = trim($query);
            
            // Adiciona localização se disponível (formato: "query location" ou "query location, Brasil")
            if ($locationName) {
                $searchQuery .= " " . trim($locationName);
            } elseif ($location) {
                $normalizedLocation = trim($location);
                // Adiciona ", Brasil" apenas se não tiver já
                if (!stripos($normalizedLocation, 'brasil') && !stripos($normalizedLocation, 'brazil')) {
                    $normalizedLocation .= ", Brasil";
                }
                $searchQuery .= " " . $normalizedLocation;
            }
            
            // Prepara os dados da requisição
            // Usa "json" => "1" conforme exemplo fornecido
            // Adiciona "num" => 100 para obter até 100 resultados (padrão é 20)
            $requestParams = [
                "engine" => "google_maps",
                "q" => trim($searchQuery), // Remove espaços extras
                "json" => "1",
                "type" => "search",
                "num" => 100 // Número máximo de resultados por página (padrão: 20, máximo: 100)
            ];
            
            // Nota: Se ainda retornar apenas 20, pode ser limitação da API ou plano
            // Nesse caso, seria necessário usar paginação com o parâmetro "start"
            
            // Adiciona coordenadas se disponíveis (formato ll="@lat,lng,zoom")
            if ($coords && isset($coords['latitude']) && isset($coords['longitude'])) {
                $requestParams['ll'] = "@{$coords['latitude']},{$coords['longitude']},14z";
            }
            
            // Log da query montada para debug
            error_log("=== REQUISIÇÃO SCRAPER API ===");
            error_log("Query montada: " . $requestParams['q']);
            error_log("Parâmetro 'num': " . $requestParams['num'] . " (tipo: " . gettype($requestParams['num']) . ")");
            error_log("Dados completos: " . json_encode($requestParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            error_log("=== FIM REQUISIÇÃO ===");
            
            // Faz a requisição
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->baseUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0, // Usa 0 como no exemplo (sem timeout)
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($requestParams),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/x-www-form-urlencoded'
                ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            if ($error) {
                throw new Exception("Erro na requisição: $error");
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? $errorData['message'] ?? "Erro HTTP $httpCode";
                
                if ($httpCode === 429) {
                    throw new Exception("Muitas requisições. Aguarde 1 minuto.");
                }
                
                if ($httpCode === 401) {
                    throw new Exception("Chave de API inválida. Verifique a configuração.");
                }
                
                throw new Exception("Erro na API: $errorMsg");
            }
            
            // Log da resposta bruta para debug (primeiros caracteres)
            error_log("Resposta bruta ScraperAPI (primeiros 1000 chars): " . substr($response, 0, 1000));
            error_log("Tipo da resposta: " . gettype($response) . ", Tamanho: " . strlen($response));
            
            // Verifica se a resposta parece ser HTML ou outro formato não-JSON
            $trimmedResponse = trim($response);
            if (empty($trimmedResponse)) {
                throw new Exception("Resposta vazia da API");
            }
            
            // Tenta detectar se é HTML
            if (stripos($trimmedResponse, '<html') !== false || stripos($trimmedResponse, '<!DOCTYPE') !== false) {
                error_log("Resposta parece ser HTML. Primeiros 500 chars: " . substr($trimmedResponse, 0, 500));
                throw new Exception("A API retornou HTML em vez de JSON. Verifique a URL e os parâmetros da requisição.");
            }
            
            // Remove o prefixo ")]}'\n" se existir (formato de segurança do Google)
            $cleanResponse = $response;
            if (strpos($cleanResponse, ")]}'\n") === 0) {
                $cleanResponse = substr($cleanResponse, 5);
            }
            
            // Decodifica a resposta JSON
            $data = json_decode($cleanResponse, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log da resposta bruta para debug
                error_log("Erro ao decodificar JSON: " . json_last_error_msg());
                error_log("Resposta da ScraperAPI (primeiros 1000 chars): " . substr($cleanResponse, 0, 1000));
                throw new Exception("Resposta inválida da API (não é JSON válido): " . json_last_error_msg());
            }
            
            // Se a resposta decodificada for uma string, tenta decodificar novamente
            // Isso pode acontecer se a API retornar uma string JSON dentro de outra string JSON
            if (is_string($data)) {
                error_log("Resposta decodificada é uma string. Tamanho: " . strlen($data));
                error_log("Primeiros 200 chars da string: " . substr($data, 0, 200));
                
                // Remove o prefixo ")]}'\n" se existir
                $cleanData = $data;
                if (strpos($cleanData, ")]}'\n") === 0) {
                    $cleanData = substr($cleanData, 5);
                }
                
                // Tenta decodificar como JSON novamente
                $decodedAgain = json_decode($cleanData, true);
                
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decodedAgain) || is_object($decodedAgain))) {
                    // Se conseguiu decodificar e é array/objeto, usa esse resultado
                    $data = $decodedAgain;
                    error_log("String JSON decodificada com sucesso. Tipo resultante: " . gettype($data));
                    
                    // Verifica se tem local_results no resultado decodificado
                    if (is_array($data) && isset($data['local_results'])) {
                        error_log("✓ local_results encontrado após decodificar string JSON!");
                    } else {
                        error_log("✗ local_results NÃO encontrado após decodificar string JSON.");
                    }
                } else {
                    // Se não conseguiu decodificar, NÃO tenta processar dados brutos
                    // Retorna vazio em vez de lançar exceção - deixa o search.php tratar
                    error_log("⚠️ Erro ao decodificar string JSON: " . json_last_error_msg());
                    error_log("Primeiros 500 chars da string: " . substr($cleanData, 0, 500));
                    error_log("NÃO processando dados brutos para evitar dados incorretos. Retornando array vazio.");
                    // Retorna vazio em vez de lançar exceção
                    return [];
                }
            }
            
            // Verifica se a resposta é um array ou objeto
            if (!is_array($data) && !is_object($data)) {
                error_log("Resposta da ScraperAPI não é array/objeto. Tipo: " . gettype($data) . ". Valor: " . var_export($data, true));
                throw new Exception("Resposta da API em formato inválido. Esperado array/objeto, recebido: " . gettype($data));
            }
            
            // Converte objeto para array se necessário
            if (is_object($data)) {
                $data = (array) $data;
            }
            
            // Log da estrutura para debug (sempre ativo)
            error_log("=== ESTRUTURA DA RESPOSTA SCRAPERAPI ===");
            error_log("Tipo: " . gettype($data));
            if (is_array($data)) {
                error_log("Chaves principais: " . json_encode(array_keys($data)));
                if (isset($data['local_results'])) {
                    error_log("local_results encontrado! Tipo: " . gettype($data['local_results']) . ", Count: " . (is_array($data['local_results']) ? count($data['local_results']) : 'N/A'));
                } else {
                    error_log("local_results NÃO encontrado!");
                }
                // Log de uma amostra da estrutura (primeiros 2000 chars)
                error_log("Amostra da estrutura (primeiros 2000 chars): " . substr(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 2000));
            }
            error_log("=== FIM ESTRUTURA ===");
            
            // Converte a resposta para o formato esperado
            $leads = $this->parseResponse($data, $locationName ?: $location);
            
            // Verifica quantos resultados brutos a API retornou (antes do processamento)
            $rawResultsCount = isset($data['local_results']) && is_array($data['local_results']) ? count($data['local_results']) : 0;
            error_log("Resultados brutos da API: $rawResultsCount | Leads processados: " . count($leads));
            
            // Se retornou exatamente 20 resultados brutos e queremos mais, tenta paginação
            if ($rawResultsCount === 20 && $maxResults > 20 && count($leads) < $maxResults) {
                error_log("⚠️ API retornou exatamente 20 resultados brutos. Iniciando paginação automática...");
                
                try {
                    // Segunda requisição com start=20 para pegar os próximos resultados
                    $dataPage2 = $requestParams;
                    $dataPage2['start'] = 20;
                    
                    $curl2 = curl_init();
                    curl_setopt_array($curl2, array(
                        CURLOPT_URL => $this->baseUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => http_build_query($dataPage2),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: Bearer ' . $this->apiKey,
                            'Content-Type: application/x-www-form-urlencoded'
                        ),
                    ));
                    
                    $response2 = curl_exec($curl2);
                    $httpCode2 = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
                    curl_close($curl2);
                    
                    if ($httpCode2 === 200) {
                        $cleanResponse2 = $response2;
                        if (strpos($cleanResponse2, ")]}'\n") === 0) {
                            $cleanResponse2 = substr($cleanResponse2, 5);
                        }
                        
                        $data2 = json_decode($cleanResponse2, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($data2['local_results'])) {
                            $leadsPage2 = $this->parseResponse($data2, $locationName ?: $location);
                            $leads = array_merge($leads, $leadsPage2);
                            error_log("Segunda página retornou " . count($leadsPage2) . " resultados. Total: " . count($leads));
                            
                            // Se ainda precisar de mais, tenta uma terceira página
                            $rawResultsCount2 = isset($data2['local_results']) && is_array($data2['local_results']) ? count($data2['local_results']) : 0;
                            if (count($leads) < $maxResults && $rawResultsCount2 === 20) {
                                error_log("Segunda página também retornou 20 resultados. Buscando terceira página...");
                                $dataPage3 = $requestParams;
                                $dataPage3['start'] = 40;
                                
                                $curl3 = curl_init();
                                curl_setopt_array($curl3, array(
                                    CURLOPT_URL => $this->baseUrl,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => http_build_query($dataPage3),
                                    CURLOPT_HTTPHEADER => array(
                                        'Authorization: Bearer ' . $this->apiKey,
                                        'Content-Type: application/x-www-form-urlencoded'
                                    ),
                                ));
                                
                                $response3 = curl_exec($curl3);
                                $httpCode3 = curl_getinfo($curl3, CURLINFO_HTTP_CODE);
                                curl_close($curl3);
                                
                                if ($httpCode3 === 200) {
                                    $cleanResponse3 = $response3;
                                    if (strpos($cleanResponse3, ")]}'\n") === 0) {
                                        $cleanResponse3 = substr($cleanResponse3, 5);
                                    }
                                    
                                    $data3 = json_decode($cleanResponse3, true);
                                    if (json_last_error() === JSON_ERROR_NONE && isset($data3['local_results'])) {
                                        $leadsPage3 = $this->parseResponse($data3, $locationName ?: $location);
                                        $leads = array_merge($leads, $leadsPage3);
                                        $rawResultsCount3 = count($data3['local_results']);
                                        error_log("Terceira página retornou $rawResultsCount3 resultados brutos, " . count($leadsPage3) . " processados. Total acumulado: " . count($leads));
                                        
                                        // Pode continuar buscando mais páginas se necessário
                                        if (count($leads) < $maxResults && $rawResultsCount3 === 20) {
                                            error_log("Terceira página também retornou 20. Buscando quarta página...");
                                            $dataPage4 = $requestParams;
                                            $dataPage4['start'] = 60;
                                            
                                            $curl4 = curl_init();
                                            curl_setopt_array($curl4, array(
                                                CURLOPT_URL => $this->baseUrl,
                                                CURLOPT_RETURNTRANSFER => true,
                                                CURLOPT_ENCODING => '',
                                                CURLOPT_MAXREDIRS => 10,
                                                CURLOPT_TIMEOUT => 0,
                                                CURLOPT_FOLLOWLOCATION => true,
                                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                                CURLOPT_CUSTOMREQUEST => 'POST',
                                                CURLOPT_POSTFIELDS => http_build_query($dataPage4),
                                                CURLOPT_HTTPHEADER => array(
                                                    'Authorization: Bearer ' . $this->apiKey,
                                                    'Content-Type: application/x-www-form-urlencoded'
                                                ),
                                            ));
                                            
                                            $response4 = curl_exec($curl4);
                                            $httpCode4 = curl_getinfo($curl4, CURLINFO_HTTP_CODE);
                                            curl_close($curl4);
                                            
                                            if ($httpCode4 === 200) {
                                                $cleanResponse4 = $response4;
                                                if (strpos($cleanResponse4, ")]}'\n") === 0) {
                                                    $cleanResponse4 = substr($cleanResponse4, 5);
                                                }
                                                
                                                $data4 = json_decode($cleanResponse4, true);
                                                if (json_last_error() === JSON_ERROR_NONE && isset($data4['local_results'])) {
                                                    $leadsPage4 = $this->parseResponse($data4, $locationName ?: $location);
                                                    $leads = array_merge($leads, $leadsPage4);
                                                    $rawResultsCount4 = count($data4['local_results']);
                                                    error_log("Quarta página retornou $rawResultsCount4 resultados brutos, " . count($leadsPage4) . " processados. Total acumulado: " . count($leads));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erro ao buscar página adicional: " . $e->getMessage());
                    // Continua com os resultados da primeira página
                }
            }
            
            // Limita ao máximo desejado
            if (count($leads) > $maxResults) {
                $leads = array_slice($leads, 0, $maxResults);
            }
            
            // Filtra nomes excluídos
            if (!empty($excludeNames)) {
                $leads = array_filter($leads, function($lead) use ($excludeNames) {
                    return !in_array(strtolower($lead['name']), array_map('strtolower', $excludeNames));
                });
            }
            
            return array_values($leads);
            
        } catch (Exception $e) {
            error_log("Erro ScraperAPI: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Converte a resposta da API para o formato esperado
     */
    private function parseResponse($data, $location) {
        $leads = [];
        
        // A estrutura da resposta pode variar, então tentamos diferentes formatos
        $results = [];
        
        // Tenta diferentes estruturas de resposta comuns em APIs de scraping
        // Prioriza local_results que é o formato padrão da ScraperAPI
        if (isset($data['local_results']) && is_array($data['local_results'])) {
            $results = $data['local_results'];
            $totalResults = count($results);
            error_log("✓ Formato local_results detectado. Total de resultados retornados pela API: " . $totalResults);
            
            // Se retornou exatamente 20, pode ser que o parâmetro num não esteja sendo respeitado
            if ($totalResults === 20) {
                error_log("⚠️ ATENÇÃO: API retornou exatamente 20 resultados. O parâmetro 'num' pode não estar sendo respeitado.");
            }
            
            // Log do primeiro resultado para debug (sempre ativo)
            if (count($results) > 0) {
                $firstResult = $results[0];
                error_log("=== DEBUG PARSE RESPONSE ===");
                error_log("Total de resultados: " . count($results));
                error_log("Primeiro resultado - Tipo: " . gettype($firstResult));
                if (is_array($firstResult)) {
                    error_log("Primeiro resultado - Chaves: " . json_encode(array_keys($firstResult)));
                    error_log("Primeiro resultado - Title: " . (isset($firstResult['title']) ? var_export($firstResult['title'], true) : 'NÃO ENCONTRADO'));
                    error_log("Primeiro resultado - Phone: " . (isset($firstResult['phone']) ? var_export($firstResult['phone'], true) : 'NÃO ENCONTRADO'));
                    error_log("Primeiro resultado - Address: " . (isset($firstResult['address']) ? var_export($firstResult['address'], true) : 'NÃO ENCONTRADO'));
                    error_log("Primeiro resultado completo (JSON): " . json_encode($firstResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } else {
                    error_log("Primeiro resultado não é array! Valor: " . var_export($firstResult, true));
                }
                error_log("=== FIM DEBUG ===");
            }
        } elseif (isset($data['organic_results']) && is_array($data['organic_results'])) {
            $results = $data['organic_results'];
        } elseif (isset($data['places']) && is_array($data['places'])) {
            $results = $data['places'];
        } elseif (isset($data['businesses']) && is_array($data['businesses'])) {
            $results = $data['businesses'];
        } elseif (isset($data['results']) && is_array($data['results'])) {
            $results = $data['results'];
        } elseif (isset($data['d']) && is_string($data['d'])) {
            // Formato do Google Maps: {"c":0,"d":"string_json_complexa"}
            // MAS PRIMEIRO: verifica se a string 'd' contém um JSON válido com local_results
            error_log("Campo 'd' detectado (string). Tamanho: " . strlen($data['d']));
            
            // Remove o prefixo ")]}'\n" se existir
            $cleanData = $data['d'];
            if (strpos($cleanData, ")]}'\n") === 0) {
                $cleanData = substr($cleanData, 5);
            }
            
            // Tenta decodificar como JSON
            $innerData = json_decode($cleanData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($innerData)) {
                // Se conseguiu decodificar, verifica se tem local_results
                if (isset($innerData['local_results']) && is_array($innerData['local_results'])) {
                    error_log("local_results encontrado dentro do campo 'd'!");
                    $results = $innerData['local_results'];
                } else {
                    // Se não tem local_results, não tenta processar dados brutos
                    // Retorna vazio para evitar dados incorretos
                    error_log("String 'd' decodificada, mas SEM local_results. Retornando vazio para evitar dados incorretos.");
                    $results = [];
                }
            } else {
                // Se não conseguiu decodificar, não tenta processar dados brutos
                // Retorna vazio para evitar dados incorretos
                error_log("Erro ao decodificar string 'd': " . json_last_error_msg());
                error_log("NÃO processando dados brutos para evitar dados incorretos.");
                $results = [];
            }
        } elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
            // Se for um array direto de resultados
            $results = $data;
        } else {
            // Log para debug se não encontrar estrutura conhecida
            if (is_array($data)) {
                $keys = array_keys($data);
                error_log("⚠️ Estrutura de resposta não reconhecida. Chaves disponíveis: " . json_encode($keys));
                
                // Tenta procurar local_results em qualquer nível da estrutura
                $foundLocalResults = false;
                foreach ($data as $key => $value) {
                    if (is_array($value) && isset($value['local_results']) && is_array($value['local_results'])) {
                        error_log("local_results encontrado dentro da chave '$key'!");
                        $results = $value['local_results'];
                        $foundLocalResults = true;
                        break;
                    }
                }
                
                if (!$foundLocalResults) {
                    error_log("⚠️ Estrutura de resposta não reconhecida e local_results não encontrado.");
                    error_log("Chaves disponíveis: " . json_encode($keys));
                    error_log("Estrutura completa (primeiros 2000 chars): " . substr(json_encode($data), 0, 2000));
                    // Retorna vazio em vez de lançar exceção - deixa o search.php tratar
                    return [];
                }
            } else {
                error_log("Estrutura de resposta não reconhecida. Tipo recebido: " . gettype($data) . ". Valor: " . json_encode($data));
                return [];
            }
        }
        
        foreach ($results as $idx => $item) {
            // Garante que $item é um array
            if (!is_array($item)) {
                error_log("Item $idx não é um array. Tipo: " . gettype($item));
                continue;
            }
            
            // Log do primeiro item para debug (sempre ativo)
            if ($idx === 0) {
                error_log("=== DEBUG ITEM PROCESSING ===");
                error_log("Item $idx (chaves): " . json_encode(array_keys($item)));
                error_log("Item $idx (title): " . (isset($item['title']) ? var_export($item['title'], true) : 'NÃO EXISTE'));
                error_log("Item $idx (phone): " . (isset($item['phone']) ? var_export($item['phone'], true) : 'NÃO EXISTE'));
                error_log("Item $idx (address): " . (isset($item['address']) ? var_export($item['address'], true) : 'NÃO EXISTE'));
                error_log("=== FIM DEBUG ITEM ===");
            }
            
            // Extrai os dados do item (formato da ScraperAPI)
            $name = '';
            // Prioriza 'title' que é o campo padrão da ScraperAPI
            if (isset($item['title'])) {
                if (is_string($item['title'])) {
                    $name = trim($item['title']);
                } elseif (is_scalar($item['title'])) {
                    $name = trim((string)$item['title']);
                }
            } elseif (isset($item['name']) && is_string($item['name'])) {
                $name = trim($item['name']);
            } elseif (isset($item['business_name']) && is_string($item['business_name'])) {
                $name = trim($item['business_name']);
            }
            
            // Log se o nome estiver vazio após extração
            if (empty($name) && $idx < 3) {
                error_log("⚠️ Nome vazio no item $idx. Chaves disponíveis: " . json_encode(array_keys($item)));
            }
            
            $address = '';
            if (isset($item['address']) && is_string($item['address'])) {
                $address = trim($item['address']);
            } elseif (isset($item['full_address']) && is_string($item['full_address'])) {
                $address = trim($item['full_address']);
            } elseif (isset($item['location']) && is_string($item['location'])) {
                $address = trim($item['location']);
            }
            
            $phone = '';
            if (isset($item['phone']) && is_string($item['phone'])) {
                $phone = trim($item['phone']);
            } elseif (isset($item['phone_number']) && is_string($item['phone_number'])) {
                $phone = trim($item['phone_number']);
            } elseif (isset($item['telephone']) && is_string($item['telephone'])) {
                $phone = trim($item['telephone']);
            }
            
            $website = '';
            if (isset($item['website']) && is_string($item['website'])) {
                $website = trim($item['website']);
            } elseif (isset($item['url']) && is_string($item['url'])) {
                $website = trim($item['url']);
            } elseif (isset($item['link']) && is_string($item['link'])) {
                $website = trim($item['link']);
            }
            
            // Extrai coordenadas (pode estar em gps_coordinates ou diretamente)
            $latitude = null;
            $longitude = null;
            if (isset($item['gps_coordinates']) && is_array($item['gps_coordinates'])) {
                $latitude = isset($item['gps_coordinates']['latitude']) ? floatval($item['gps_coordinates']['latitude']) : null;
                $longitude = isset($item['gps_coordinates']['longitude']) ? floatval($item['gps_coordinates']['longitude']) : null;
            } else {
                $latitude = isset($item['latitude']) ? floatval($item['latitude']) : (isset($item['lat']) ? floatval($item['lat']) : null);
                $longitude = isset($item['longitude']) ? floatval($item['longitude']) : (isset($item['lng']) ? floatval($item['lng']) : null);
            }
            
            $rating = isset($item['rating']) ? floatval($item['rating']) : null;
            $reviews = isset($item['reviews']) ? intval($item['reviews']) : null;
            
            // Gera mapsUri usando place_id, provider_id ou nome
            $mapsUri = '';
            if (isset($item['place_id']) && !empty($item['place_id']) && is_string($item['place_id'])) {
                // place_id é o formato mais confiável
                $mapsUri = "https://www.google.com/maps/place/?q=place_id:" . urlencode($item['place_id']);
            } elseif (isset($item['provider_id']) && !empty($item['provider_id']) && is_string($item['provider_id'])) {
                // provider_id geralmente vem como "/g/11bw4ws2mt" - converte para URL do Google Maps
                $providerId = ltrim($item['provider_id'], '/');
                if (strpos($providerId, 'g/') === 0) {
                    $mapsUri = "https://www.google.com/maps/place/?cid=" . urlencode(substr($providerId, 2));
                } else {
                    $mapsUri = "https://www.google.com/maps/search/?api=1&query=" . urlencode($name . ' ' . ($address ?: ''));
                }
            } elseif ($name) {
                $searchQuery = urlencode($name . ' ' . ($address ?: $location ?: ''));
                $mapsUri = "https://www.google.com/maps/search/?api=1&query=$searchQuery";
            }
            
            // Formata telefone (mantém formato original se já estiver formatado)
            if ($phone) {
                // Se já estiver formatado (contém parênteses ou hífen), mantém
                if (!preg_match('/[\(\)\-]/', $phone)) {
                    // Se não estiver formatado, formata
                    $phoneClean = preg_replace('/[^\d+]/', '', $phone);
                    if (preg_match('/^55(\d{10,11})$/', $phoneClean, $m)) {
                        $phoneClean = $m[1];
                    }
                    if (preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $phoneClean, $m)) {
                        $phone = "({$m[1]}) {$m[2]}-{$m[3]}";
                    } else {
                        $phone = $phoneClean;
                    }
                }
            }
            
            // Monta o lead no formato esperado
            $lead = [
                'id' => 'lead-' . time() . '-' . $idx,
                'name' => $name,
                'address' => $address,
                'phone' => $phone,
                'email' => '', // API não retorna email diretamente
                'website' => $website,
                'mapsUri' => $mapsUri,
                'cnpj' => '', // API não retorna CNPJ diretamente
                'partners' => '', // API não retorna sócios diretamente
                'tag' => '',
                'latitude' => $latitude,
                'longitude' => $longitude
            ];
            
            // Adiciona informações extras se disponíveis
            if ($rating !== null) {
                $lead['rating'] = $rating;
            }
            if ($reviews !== null) {
                $lead['reviews'] = $reviews;
            }
            
            // Validação final: só adiciona se tiver pelo menos nome válido
            // Rejeita nomes que são apenas números, índices ou strings inválidas
            $isValidName = !empty($name) && 
                          strlen($name) > 2 && 
                          $name !== 'null' && 
                          strtolower($name) !== 'null' &&
                          !is_numeric($name) &&
                          !preg_match('/^[:\d,\\\]+$/', $name) && // Rejeita ":0," ou "1769481071193\"
                          !preg_match('/^\d+$/', $name) && // Rejeita números puros
                          !preg_match('/^[\[\(\)\]\s,]+$/', $name); // Rejeita apenas caracteres especiais
            
            if ($isValidName) {
                $leads[] = $lead;
                // Log do primeiro lead adicionado
                if ($idx === 0) {
                    error_log("=== LEAD ADICIONADO ===");
                    error_log("Lead $idx - Nome: '$name'");
                    error_log("Lead $idx - Phone: '$phone'");
                    error_log("Lead $idx - Address: '$address'");
                    error_log("Lead completo: " . json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    error_log("=== FIM LEAD ===");
                }
            } else {
                // Log quando não adiciona um lead (sempre ativo)
                error_log("Lead $idx REJEITADO. Nome: '$name' (tipo: " . gettype($name) . ", len: " . strlen($name) . ")");
                error_log("Validação: empty=" . (empty($name) ? 'true' : 'false') . ", len>2=" . (strlen($name) > 2 ? 'true' : 'false') . ", is_numeric=" . (is_numeric($name) ? 'true' : 'false'));
                if ($idx < 3) { // Log apenas os 3 primeiros rejeitados
                    error_log("Item completo rejeitado: " . json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            }
        }
        
        error_log("=== RESUMO PARSE RESPONSE ===");
        error_log("Total de resultados recebidos: " . count($results));
        error_log("Total de leads processados e adicionados: " . count($leads));
        if (count($results) > 0 && count($leads) === 0) {
            error_log("⚠️ ATENÇÃO: Recebeu " . count($results) . " resultados mas nenhum lead foi adicionado!");
            error_log("Isso pode indicar que todos os resultados foram rejeitados pela validação.");
        }
        error_log("=== FIM RESUMO ===");
        return $leads;
    }
    
    /**
     * Extrai dados de negócios do formato complexo do Google Maps
     */
    private function extractBusinessesFromGoogleMapsFormat($data, $depth = 0, $foundBusinesses = []) {
        // Limita a profundidade para evitar loops infinitos
        if ($depth > 25) {
            return $foundBusinesses;
        }
        
        if (!is_array($data)) {
            return $foundBusinesses;
        }
        
        // Procura por padrões específicos do formato do Google Maps
        // O formato do Google Maps geralmente tem arrays aninhados onde os dados estão em posições específicas
        
        // Procura por arrays que parecem conter dados de negócios
        // Padrão: arrays com múltiplos elementos, incluindo strings (nomes) e arrays aninhados (dados)
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Se for um array com muitos elementos, pode conter dados de negócio
                if (count($value) >= 5) {
                    $business = $this->parseGoogleMapsBusinessItem($value);
                    if (!empty($business['name']) && strlen($business['name']) > 2) {
                        // Verifica se já não existe (evita duplicatas)
                        $exists = false;
                        foreach ($foundBusinesses as $existing) {
                            if (strtolower(trim($existing['name'])) === strtolower(trim($business['name']))) {
                                $exists = true;
                                // Mescla dados se o novo tiver mais informações
                                if (empty($existing['address']) && !empty($business['address'])) {
                                    $existing['address'] = $business['address'];
                                }
                                if (empty($existing['phone']) && !empty($business['phone'])) {
                                    $existing['phone'] = $business['phone'];
                                }
                                break;
                            }
                        }
                        if (!$exists) {
                            $foundBusinesses[] = $business;
                        }
                    }
                }
                
                // Continua procurando recursivamente em arrays menores também
                if (count($value) >= 2) {
                    $foundBusinesses = $this->extractBusinessesFromGoogleMapsFormat($value, $depth + 1, $foundBusinesses);
                }
            }
        }
        
        return $foundBusinesses;
    }
    
    /**
     * Tenta extrair dados de um item que pode ser um negócio do Google Maps
     */
    private function parseGoogleMapsBusinessItem($item) {
        $business = [
            'name' => '',
            'address' => '',
            'phone' => '',
            'website' => '',
            'mapsUri' => '',
            'latitude' => null,
            'longitude' => null,
            'rating' => null,
            'reviews' => null
        ];
        
        if (!is_array($item)) {
            return $business;
        }
        
        // Coleta todos os dados do array de forma recursiva
        $allStrings = [];
        $allNumbers = [];
        $allArrays = [];
        
        $this->collectDataFromArray($item, $allStrings, $allNumbers, $allArrays);
        
        // Remove duplicatas
        $allStrings = array_unique($allStrings);
        
        // Procura nome (primeira string que parece um nome de negócio válido)
        foreach ($allStrings as $str) {
            $str = trim($str);
            if (strlen($str) > 2 && strlen($str) < 200 && 
                !preg_match('/^https?:\/\//', $str) && 
                !preg_match('/^\d+$/', $str) &&
                !preg_match('/^[a-z0-9_]{20,}$/i', $str) && // IDs longos
                $str !== 'null' && 
                $str !== 'true' && 
                $str !== 'false' &&
                !preg_match('/^[A-Z0-9_]{30,}$/', $str) && // IDs em maiúsculas
                empty($business['name'])) {
                // Verifica se não é um endereço
                if (!preg_match('/\d{5}-?\d{3}/', $str) && 
                    stripos($str, 'rua') === false && 
                    stripos($str, 'av.') === false) {
                    $business['name'] = $str;
                    break;
                }
            }
        }
        
        // Procura endereço (strings que contêm palavras-chave de endereço ou CEP)
        foreach ($allStrings as $str) {
            $str = trim($str);
            if (empty($business['address']) && strlen($str) > 5 &&
                (stripos($str, 'rua') !== false || 
                 stripos($str, 'av.') !== false || 
                 stripos($str, 'avenida') !== false ||
                 stripos($str, 'quadra') !== false ||
                 stripos($str, 'lote') !== false ||
                 stripos($str, 'anápolis') !== false ||
                 preg_match('/\d{5}-?\d{3}/', $str) ||
                 preg_match('/- GO,?\s*\d{5}/', $str) ||
                 preg_match('/\d{4,5}-?\d{4}/', $str))) {
                $business['address'] = $str;
                break;
            }
        }
        
        // Procura telefone (formato brasileiro)
        foreach ($allStrings as $str) {
            $str = trim($str);
            if (empty($business['phone']) && 
                (preg_match('/\(?\d{2}\)?\s?\d{4,5}-?\d{4}/', $str) ||
                 preg_match('/\+?55\s?\d{2}\s?\d{4,5}-?\d{4}/', $str))) {
                $business['phone'] = $str;
                break;
            }
        }
        
        // Procura website
        foreach ($allStrings as $str) {
            $str = trim($str);
            if (empty($business['website']) && 
                preg_match('/^https?:\/\//', $str)) {
                $business['website'] = $str;
                break;
            }
        }
        
        // Procura coordenadas nos arrays aninhados (formato [lat, lng])
        foreach ($allArrays as $arr) {
            if (is_array($arr) && count($arr) >= 2 && 
                is_numeric($arr[0]) && is_numeric($arr[1]) &&
                $arr[0] >= -90 && $arr[0] <= 90 && 
                $arr[1] >= -180 && $arr[1] <= 180) {
                // Verifica se são coordenadas válidas do Brasil (aproximadamente)
                if ($arr[0] >= -35 && $arr[0] <= 5 && $arr[1] >= -75 && $arr[1] <= -30) {
                    $business['latitude'] = floatval($arr[0]);
                    $business['longitude'] = floatval($arr[1]);
                    break;
                }
            }
        }
        
        // Procura rating (0-5) e reviews (números maiores)
        foreach ($allNumbers as $num) {
            if (is_numeric($num)) {
                $num = floatval($num);
                if ($num > 0 && $num <= 5 && $business['rating'] === null) {
                    $business['rating'] = $num;
                } elseif ($num > 5 && $num < 1000000 && $business['reviews'] === null) {
                    $business['reviews'] = intval($num);
                }
            }
        }
        
        return $business;
    }
    
    /**
     * Coleta recursivamente strings, números e arrays de uma estrutura aninhada
     */
    private function collectDataFromArray($data, &$strings, &$numbers, &$arrays, $depth = 0) {
        if ($depth > 10 || !is_array($data)) {
            return;
        }
        
        foreach ($data as $val) {
            if (is_string($val) && strlen($val) > 1) {
                $strings[] = $val;
            } elseif (is_numeric($val)) {
                $numbers[] = $val;
            } elseif (is_array($val)) {
                $arrays[] = $val;
                $this->collectDataFromArray($val, $strings, $numbers, $arrays, $depth + 1);
            }
        }
    }
    
    /**
     * Tenta extrair negócios diretamente da string raw do Google Maps usando regex
     */
    private function extractBusinessesFromRawGoogleMaps($rawString) {
        $businesses = [];
        $foundNames = [];
        
        // Procura por nomes de negócios (strings entre aspas que parecem nomes)
        // Padrão: strings com 3-100 caracteres que não são URLs, números ou IDs
        if (preg_match_all('/"([^"]{3,100})"/', $rawString, $matches)) {
            foreach ($matches[1] as $match) {
                $match = trim($match);
                
                // Filtra strings que parecem nomes de negócios
                if (strlen($match) >= 3 && strlen($match) <= 100 &&
                    !preg_match('/^https?:\/\//', $match) && 
                    !preg_match('/^\d+$/', $match) &&
                    !preg_match('/^[a-z0-9_]{20,}$/i', $match) &&
                    !preg_match('/^[A-Z0-9_]{30,}$/', $match) &&
                    $match !== 'null' && 
                    $match !== 'true' && 
                    $match !== 'false' &&
                    !preg_match('/^\d{2}:\d{2}/', $match) && // Horários
                    !preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/', $match) && // Datas
                    !in_array(strtolower($match), $foundNames)) {
                    
                    // Verifica se não é um endereço completo
                    if (!preg_match('/\d{5}-?\d{3}/', $match) || 
                        (stripos($match, 'rua') === false && 
                         stripos($match, 'av.') === false &&
                         stripos($match, 'quadra') === false)) {
                        
                        $foundNames[] = strtolower($match);
                        $business = [
                            'name' => $match,
                            'address' => '',
                            'phone' => '',
                            'website' => '',
                            'mapsUri' => '',
                            'latitude' => null,
                            'longitude' => null,
                            'rating' => null,
                            'reviews' => null
                        ];
                        $businesses[] = $business;
                    }
                }
            }
        }
        
        // Tenta encontrar endereços e telefones para associar aos negócios
        // Procura por endereços (strings com CEP ou palavras-chave)
        if (preg_match_all('/"([^"]*rua[^"]*|\d{5}-?\d{3}[^"]*|av\.[^"]*|avenida[^"]*|quadra[^"]*)"/i', $rawString, $addressMatches)) {
            foreach ($addressMatches[1] as $addr) {
                foreach ($businesses as &$biz) {
                    if (empty($biz['address']) && strlen($addr) > 10) {
                        $biz['address'] = $addr;
                        break;
                    }
                }
            }
        }
        
        // Procura por telefones
        if (preg_match_all('/"\(?\d{2}\)?\s?\d{4,5}-?\d{4}"/', $rawString, $phoneMatches)) {
            foreach ($phoneMatches[0] as $phone) {
                $phone = trim($phone, '"');
                foreach ($businesses as &$biz) {
                    if (empty($biz['phone'])) {
                        $biz['phone'] = $phone;
                        break;
                    }
                }
            }
        }
        
        return $businesses;
    }
}
