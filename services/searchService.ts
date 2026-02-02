import { Lead, TokenUsage } from '../types';

/**
 * Serviço de Busca de Leads (Google Maps)
 * Chama o endpoint PHP que utiliza o ScraperService
 */

const API_BASE = '/MapsProspector-Pro/api/';

export interface SearchResult {
  leads: Lead[];
  tokenUsage?: TokenUsage;
  searchId?: string;
}

export const searchLeadsOnMaps = async (
  query: string,
  location?: string,
  excludeNames: string[] = [],
  coords?: { latitude: number; longitude: number },
  locationName?: string,
  maxCrawledPlacesPerSearch?: number
): Promise<SearchResult> => {
  try {
    const response = await fetch(API_BASE + 'search.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include', // Inclui cookies para sessão PHP
      body: JSON.stringify({
        query,
        location: location || null,
        tag: '',
        useGPS: !!coords,
        coords: coords || null,
        locationName: locationName || null,
        maxCrawledPlacesPerSearch: maxCrawledPlacesPerSearch != null ? Math.max(1, Math.min(1000, maxCrawledPlacesPerSearch)) : undefined,
      }),
    });

    // Verifica se a resposta está OK
    if (!response.ok) {
      const text = await response.text();
      let errorMsg = `Erro ${response.status}: Erro interno do servidor`;
      try {
        if (text) {
          const errorData = JSON.parse(text);
          errorMsg = errorData.error || errorData.message || errorMsg;
        }
      } catch (e) {
        // Se não conseguir parsear, usa a mensagem padrão
      }
      throw new Error(errorMsg);
    }

    // Verifica se há conteúdo antes de parsear
    const text = await response.text();
    if (!text || text.trim() === '') {
      throw new Error('Erro: Resposta vazia do servidor.');
    }

    const data = JSON.parse(text);

    if (!data.success) {
      throw new Error(data.error || 'Erro ao buscar leads');
    }

    // Retorna os leads do formato da API (podem vir bloqueados: locked + encrypted_data)
    let leads: Lead[] = (data.data?.leads || []).map((lead: any) => ({
      id: lead.id || `lead-${Date.now()}-${Math.random()}`,
      name: lead.name || '',
      address: lead.locked ? undefined : (lead.address || ''),
      phone: lead.locked ? undefined : (lead.phone || ''),
      email: lead.locked ? undefined : (lead.email || ''),
      website: lead.locked ? undefined : (lead.website || ''),
      mapsUri: lead.locked ? undefined : (lead.mapsUri || lead.maps_uri || ''),
      cnpj: lead.locked ? undefined : (lead.cnpj || ''),
      partners: lead.locked ? undefined : (lead.partners || ''),
      latitude: lead.latitude,
      longitude: lead.longitude,
      sources: lead.sources || [],
      locked: !!lead.locked,
      encrypted_data: lead.encrypted_data,
      dbId: lead.dbId,
    }));

    // Filtra nomes excluídos
    if (excludeNames.length > 0) {
      leads = leads.filter(
        (lead) => !excludeNames.some((name) => lead.name.toLowerCase().includes(name.toLowerCase()))
      );
    }

    const tokenUsage: TokenUsage | undefined = data.data?.tokenUsage
      ? {
          used: data.data.tokenUsage.used,
          limit: data.data.tokenUsage.limit,
          limitReached: !!data.data.tokenUsage.limitReached,
        }
      : undefined;

    const searchId: string | undefined = data.data?.searchId;

    return { leads, tokenUsage, searchId };
  } catch (error: any) {
    console.error('Erro ao buscar leads:', error);
    throw error;
  }
};
