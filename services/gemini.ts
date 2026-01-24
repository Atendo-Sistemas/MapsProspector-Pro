
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
  coords?: { latitude: number; longitude: number }
): Promise<Lead[]> => {
  const apiKey = process.env.API_KEY;
  if (!apiKey) throw new Error("Chave de API não configurada.");

  const ai = new GoogleGenAI({ apiKey: apiKey });

  // Add country context for better accuracy
  const normalizedLocation = location && !location.toLowerCase().includes('brasil') ? `${location}, Brasil` : location;

  let searchInstruction = "";
  if (coords) {
    searchInstruction = `O usuário está nas coordenadas Lat: ${coords.latitude}, Lng: ${coords.longitude}. Use a ferramenta googleMaps para buscar "${query}" estritamente num raio próximo a estas coordenadas.`;
  } else {
    searchInstruction = `O usuário quer buscar em "${normalizedLocation}". Use a ferramenta googleMaps com a query: "${query} em ${normalizedLocation}".`;
  }

  const prompt = `Você é um especialista em mineração de dados B2B (Business to Business).
  
  TAREFA: Encontrar leads comerciais reais e atualizados usando o Google Maps.
  
  CONTEXTO DE BUSCA:
  ${searchInstruction}
  
  REGRAS OBRIGATÓRIAS:
  1. Use 'googleMaps' para validar a existência da empresa.
  2. Extraia: Nome da Empresa, Endereço Completo, Telefone (formato internacional +55...), Website e Link do Google Maps.
  3. Se o e-mail não estiver no Maps, tente encontrar via 'googleSearch' ou deixe vazio.
  4. Retorne apenas empresas que correspondem à busca.
  5. Ignore estas empresas se encontradas: ${excludeNames.join(', ') || 'nenhuma'}.
  
  FORMATO JSON ESTRITO DE RESPOSTA:
  [
    {
      "name": "Nome Comercial",
      "address": "Rua Exemplo, 123 - Bairro, Cidade - UF",
      "phone": "+5511999999999",
      "email": "contato@empresa.com",
      "website": "https://...",
      "mapsUri": "https://maps.google...",
      "latitude": 0,
      "longitude": 0
    }
  ]
  
  Retorne APENAS o JSON. Sem texto antes ou depois.`;

  const executeSearch = async (model: string) => {
     // Gemini 2.0 Flash supports googleSearch.
     // Google Maps grounding is specifically supported in 2.0-flash and 2.5-flash series.
     return await ai.models.generateContent({
      model: model, 
      contents: prompt,
      config: {
        tools: [{ googleSearch: {} }], // Fallback to search if maps tool fails specific config
        // Note: For strict Maps grounding, we rely on the prompt instructing the model to use its internal maps capability or search grounding to find maps links.
      },
    });
  };

  try {
    let response;
    
    // Attempt with selected model first
    try {
        response = await executeSearch(modelName);
    } catch (err: any) {
        console.warn(`Erro com modelo ${modelName}:`, err.message);
        // Fallback Strategy
        if (err.message.includes("404") || err.message.includes("not found")) {
            console.log("Tentando fallback para gemini-2.0-flash...");
            response = await executeSearch('gemini-2.0-flash');
        } else {
            throw err;
        }
    }

    const text = response?.text || "";
    const parsed = extractJson(text);
    
    if (parsed && Array.isArray(parsed) && parsed.length > 0) {
      return parsed.map((item: any, idx: number) => ({
        id: `lead-${Date.now()}-${idx}`,
        ...item,
        sources: response?.candidates?.[0]?.groundingMetadata?.groundingChunks?.map(c => ({ 
          title: c.web?.title, 
          uri: c.web?.uri 
        })).filter(s => s.uri)
      }));
    }

    // If no JSON, try to extract grounding chunks as a last resort
    const chunks = response?.candidates?.[0]?.groundingMetadata?.groundingChunks || [];
    const webChunks = chunks.filter(c => c.web);

    if (webChunks.length > 0) {
      return webChunks.map((c, idx) => ({
        id: `lead-raw-${Date.now()}-${idx}`,
        name: c.web?.title || "Lead Encontrado",
        address: "Verificar no link",
        mapsUri: c.web?.uri,
        phone: "",
        email: "",
        website: c.web?.uri,
        sources: [{ title: c.web?.title, uri: c.web?.uri || "" }]
      }));
    }

    return [];

  } catch (error: any) {
    console.error("Erro Gemini Final:", error);
    const errorMsg = error.toString().toLowerCase();
    
    if (errorMsg.includes("429") || errorMsg.includes("quota")) {
      throw new Error("Limite de cota do Google API excedido (Erro 429). Aguarde 1 minuto.");
    }
    
    if (errorMsg.includes("404")) {
        throw new Error("Modelo de IA indisponível. Verifique a chave de API.");
    }

    throw new Error("Não foi possível buscar os leads. Tente mudar o termo de busca.");
  }
};
