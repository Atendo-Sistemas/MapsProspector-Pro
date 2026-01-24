
import { GoogleGenAI } from "@google/genai";
import { Lead } from "../types";

const extractJson = (text: string) => {
  if (!text) return null;
  try {
    let candidate = text.replace(/[\u0000-\u001F\u007F-\u009F]/g, "").trim();
    // Remove markdown code blocks if present
    const markdownMatch = candidate.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
    if (markdownMatch) {
      candidate = markdownMatch[1].trim();
    }
    // Attempt to isolate the array
    const start = candidate.indexOf('[');
    const end = candidate.lastIndexOf(']');
    if (start !== -1 && end !== -1) {
      candidate = candidate.substring(start, end + 1);
    }
    return JSON.parse(candidate);
  } catch (e) {
    return null;
  }
};

export const searchLeadsOnMaps = async (
  query: string, 
  location?: string, 
  excludeNames: string[] = [],
  modelName: string = "gemini-2.0-flash", // Default to stable 2.0
  coords?: { latitude: number; longitude: number },
  locationName?: string // Nome legível do local via GPS (ex: Centro, SP)
): Promise<Lead[]> => {
  const apiKey = process.env.API_KEY;
  if (!apiKey) throw new Error("Chave de API não configurada.");

  const ai = new GoogleGenAI({ apiKey: apiKey });

  // Add country context for better accuracy
  const normalizedLocation = location && !location.toLowerCase().includes('brasil') ? `${location}, Brasil` : location;
  
  // Lógica de Contexto de Localização
  let locationContext = "";
  if (locationName) {
     locationContext = `em toda a cidade de ${locationName} (Brasil)`; 
  } else if (coords) {
     locationContext = `num raio amplo próximo às coordenadas Lat ${coords.latitude}, Long ${coords.longitude}`;
  } else {
     locationContext = `na cidade/região de ${normalizedLocation}`;
  }

  // PROMPT HÍBRIDO: SCAPING + MAPS VALIDATION
  const prompt = `Atue como um Robô de Extração de Dados Avançado.
  
  MISSÃO: Listar empresas do ramo "${query}" localizadas ${locationContext}.
  
  FLUXO DE EXECUÇÃO OBRIGATÓRIO:
  1. BUSCA AMPLA (Google Search): Encontre nomes de empresas em diretórios, listas ("Melhores X", "GuiaMais") e redes sociais. Tente encontrar entre 20 a 50 nomes.
  2. ENRIQUECIMENTO DE DADOS (Google Maps):
     - Para CADA empresa encontrada na etapa 1, se o telefone não estiver óbvio no texto, USE A FERRAMENTA GOOGLE MAPS para buscar a ficha da empresa.
     - EXTRAIA O TELEFONE OFICIAL que consta na ficha do Maps.
     - Se o telefone não existir nem no Maps, procure no Instagram/Facebook da empresa.
  
  CRITÉRIOS DE QUALIDADE:
  - TELEFONE: É OBRIGATÓRIO tentar preencher. Se a empresa existe no Maps, o telefone provavelmetne está lá.
  - MAPS URI: Se você usou o Maps para validar, use o link real da ficha. Se não, gere o link de busca.
  
  IGNORE (JÁ LISTADOS): ${excludeNames.join(', ') || 'nenhuma'}.
  
  SAÍDA (JSON ARRAY):
  [
    {
      "name": "Nome",
      "address": "Endereço Completo",
      "phone": "Telefone (Priorize Celular/WhatsApp, mas aceite fixo se for o único)",
      "email": "Email (opcional)",
      "website": "Site/Instagram",
      "mapsUri": "Link",
      "cnpj": "CNPJ (opcional)"
    }
  ]`;

  const executeSearch = async (model: string) => {
     // Configuração das Ferramentas
     const tools: any[] = [{ googleSearch: {} }];
     
     // Adiciona Google Maps se o modelo suportar (Gemini 2.5 ou superior geralmente, mas 2.0 aceita em alguns contextos)
     // Para garantir, vamos adicionar. Se der erro, o catch pega.
     tools.push({ googleMaps: {} });

     const config: any = { tools };

     // Se tivermos coordenadas, usamos para ancorar a busca do Maps (Retrieval Config)
     if (coords) {
        config.toolConfig = {
            retrievalConfig: {
                latLng: {
                    latitude: coords.latitude,
                    longitude: coords.longitude
                }
            }
        };
     }

     return await ai.models.generateContent({
      model: model, 
      contents: prompt,
      config: config,
    });
  };

  try {
    let response;
    
    // Attempt with selected model first
    try {
        response = await executeSearch(modelName);
    } catch (err: any) {
        console.warn(`Erro com modelo ${modelName}:`, err.message);
        // Fallback: Se falhar (ex: modelo não suporta Maps), tenta sem Maps ou muda modelo
        if (err.message.includes("404") || err.message.includes("not found") || err.message.includes("tool")) {
            console.log("Tentando fallback (pode ser erro de ferramenta)...");
            // Tenta gemini-2.0-flash apenas com Search se o Maps falhar
             const fallbackAi = new GoogleGenAI({ apiKey: apiKey });
             response = await fallbackAi.models.generateContent({
                model: 'gemini-2.0-flash',
                contents: prompt,
                config: { tools: [{ googleSearch: {} }] }
             });
        } else {
            throw err;
        }
    }

    const text = response?.text || "";
    const parsed = extractJson(text);
    
    if (parsed && Array.isArray(parsed) && parsed.length > 0) {
      let finalResults = parsed;
      
      finalResults = finalResults.map((item: any, idx: number) => ({
        id: `lead-${Date.now()}-${idx}`,
        ...item,
        phone: item.phone ? item.phone.replace(/[^\d+]/g, '').replace(/^55(\d{10,11})$/, '$1').replace(/^(\d{2})(\d{4,5})(\d{4})$/, '($1) $2-$3') : '',
        mapsUri: item.mapsUri || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.name + ' ' + (locationName || location || ''))}`,
        sources: response?.candidates?.[0]?.groundingMetadata?.groundingChunks?.map(c => ({ 
          title: c.web?.title || c.maps?.title, // Suporta título de Maps
          uri: c.web?.uri || c.maps?.uri // Suporta URI de Maps
        })).filter(s => s.uri)
      }));

      return finalResults;
    }

    // Fallback para chunks crus
    const chunks = response?.candidates?.[0]?.groundingMetadata?.groundingChunks || [];
    // Agora aceitamos chunks de Maps também
    const validChunks = chunks.filter(c => c.web || c.maps);

    if (validChunks.length > 0) {
      return validChunks.map((c, idx) => ({
        id: `lead-raw-${Date.now()}-${idx}`,
        name: c.web?.title || c.maps?.title || "Resultado Encontrado",
        address: c.maps?.address || "Verificar link", // Maps fornece endereço direto
        mapsUri: c.maps?.uri || c.web?.uri,
        phone: "", // Difícil extrair do chunk cru sem processamento da IA
        email: "",
        cnpj: "",
        website: c.web?.uri || c.maps?.uri,
        sources: [{ title: c.web?.title || c.maps?.title, uri: c.web?.uri || c.maps?.uri || "" }]
      }));
    }

    return [];

  } catch (error: any) {
    console.error("Erro Gemini Final:", error);
    const errorMsg = error.toString().toLowerCase();
    
    if (errorMsg.includes("429")) {
      throw new Error("Muitas requisições. Aguarde 1 minuto.");
    }
    
    throw new Error("Não foi possível buscar os leads. Tente mudar o termo de busca.");
  }
};
