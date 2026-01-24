
import { CRMConfig, CRMContact } from '../types';

/**
 * Serviço de Integração com Atendo CRM / Evolution API / N8N
 */

const formatPhoneNumber = (phone: string): string => {
  const digits = phone.replace(/\D/g, '');
  if (digits.length < 8) return '';
  
  // Brazil logic: ensure DDI 55
  if (digits.length >= 10 && !digits.startsWith('55')) {
    return `55${digits}`;
  }
  return digits;
};

const deepClean = (obj: any): any => {
  if (Array.isArray(obj)) {
    return obj
      .map(v => (v && typeof v === 'object' ? deepClean(v) : v))
      .filter(v => v !== null && v !== undefined && v !== '');
  }
  
  const cleaned: any = {};
  Object.keys(obj).forEach(key => {
    const value = obj[key];
    if (value === null || value === undefined || value === '') return;
    
    if (Array.isArray(value)) {
      const cleanedArr = deepClean(value);
      if (cleanedArr.length > 0) cleaned[key] = cleanedArr;
    } else if (typeof value === 'object') {
      const cleanedObj = deepClean(value);
      if (Object.keys(cleanedObj).length > 0) cleaned[key] = cleanedObj;
    } else {
      cleaned[key] = value;
    }
  });
  return cleaned;
};

export const sendSingleToCRM = async (config: CRMConfig, contact: CRMContact) => {
  if (!config.baseUrl) {
    throw new Error("URL do CRM não configurada.");
  }

  // Determine if it's a direct API call or a webhook
  const isDirectWebhook = config.baseUrl.includes('/external/') || config.baseUrl.includes('/webhook/') || config.baseUrl.includes('n8n');
  
  let targetUrl = config.baseUrl;
  if (!isDirectWebhook) {
      // Standard Atendo/Evolution Create Contact Endpoint
      targetUrl = config.baseUrl.endsWith('/') ? `${config.baseUrl}createcontact` : `${config.baseUrl}/createcontact`;
  }

  // Handle CORS if proxy is enabled
  if (config.useProxy) {
    targetUrl = `https://corsproxy.io/?${encodeURIComponent(targetUrl)}`;
  }

  try {
    const formattedNumber = formatPhoneNumber(contact.number);
    let contactData: any;
    
    // Configuração do Payload
    if (config.simplifiedPayload) {
      contactData = {
        number: formattedNumber,
        name: contact.name
      };
    } else {
      contactData = {
        name: contact.name,
        number: formattedNumber,
        email: contact.email,
        tag: contact.tag,
        extraInfo: contact.extraInfo
      };
    }

    const cleanedData = deepClean(contactData);
    
    // CRITICAL FIX FOR ATENDO/EVOLUTION API ERROR 400
    // The API throws 400 if 'id', 'ticketId' or 'contactId' is sent during creation along with 'number'.
    delete cleanedData.ticketId;
    delete cleanedData.contactId;
    delete cleanedData.id; 
    delete cleanedData.messageId;

    if (!cleanedData.number) {
      throw new Error("O contato não possui um número de telefone válido.");
    }

    // Some N8N workflows expect the data wrapped in a "body" object
    const payload = config.wrapInBody ? { body: cleanedData } : cleanedData;

    const headers: HeadersInit = {
      'Content-Type': 'application/json',
    };

    if (config.token) {
      const token = config.token.trim();
      if (token.length > 0) {
        // Support both Bearer and direct API Key headers
        headers['Authorization'] = token.startsWith('Bearer ') ? token : `Bearer ${token}`;
        headers['apikey'] = token; 
      }
    }

    const response = await fetch(targetUrl, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(payload),
      mode: 'cors'
    });

    if (!response.ok) {
      const errorText = await response.text();
      let errorMsg = errorText;
      try {
        const errJson = JSON.parse(errorText);
        errorMsg = errJson.message || errJson.error || (errJson.response ? errJson.response.message : null) || errorText;
      } catch(e) {}
      
      // Filter common API errors for better UX
      if (response.status === 400) {
          throw new Error(`Erro 400 (Dados Inválidos): ${errorMsg}. Tente ativar o 'Modo Estrito' nas configurações.`);
      }
      
      throw new Error(`CRM (${response.status}): ${errorMsg}`);
    }

    return await response.json();
  } catch (error: any) {
    console.error('Falha na integração:', error);
    if (error.message === 'Failed to fetch') {
      throw new Error("Erro de Conexão: Verifique se a URL está correta e se o 'Modo Proxy' é necessário.");
    }
    throw error;
  }
};

export const sendBatchToCRM = async (config: CRMConfig, contacts: CRMContact[]) => {
  const results = { success: 0, failed: 0 };
  for (const contact of contacts) {
    try {
      await sendSingleToCRM(config, contact);
      results.success++;
    } catch (e) {
      results.failed++;
    }
    // Small delay to prevent rate limiting
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  return results;
};
