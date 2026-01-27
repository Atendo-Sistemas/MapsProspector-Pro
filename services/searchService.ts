import { Lead } from '../types';

/**
 * Serviço de Busca de Leads usando API Thordata (ScraperAPI)
 * Chama o endpoint PHP que utiliza o ScraperService
 */

const API_BASE = '/MapsProspector-Pro/api/';

export const searchLeadsOnMaps = async (
  query: string,
  location?: string,
  excludeNames: string[] = [],
  coords?: { latitude: number; longitude: number },
  locationName?: string
): Promise<Lead[]> => {
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

    // Retorna os leads do formato da API
    const leads: Lead[] = (data.data?.leads || []).map((lead: any) => ({
      id: lead.id || `lead-${Date.now()}-${Math.random()}`,
      name: lead.name || '',
      address: lead.address || '',
      phone: lead.phone || '',
      email: lead.email || '',
      website: lead.website || '',
      mapsUri: lead.mapsUri || lead.maps_uri || '',
      cnpj: lead.cnpj || '',
      partners: lead.partners || '',
      latitude: lead.latitude,
      longitude: lead.longitude,
      sources: lead.sources || [],
    }));

    // Filtra nomes excluídos
    if (excludeNames.length > 0) {
      return leads.filter(
        (lead) => !excludeNames.some((name) => lead.name.toLowerCase().includes(name.toLowerCase()))
      );
    }

    return leads;
  } catch (error: any) {
    console.error('Erro ao buscar leads:', error);
    throw error;
  }
};
