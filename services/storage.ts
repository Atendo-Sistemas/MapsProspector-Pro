
import { CRMConfig, SearchHistoryItem } from '../types';

const KEYS = {
  CONFIG: 'atendo_maps_config',
  HISTORY: 'atendo_maps_history'
};

const DEFAULT_CONFIG: CRMConfig = {
  baseUrl: '',
  token: '',
  useProxy: false,
  wrapInBody: false,
  simplifiedPayload: false,
  tenantName: 'Atendo CRM',
  scraperApiKey: '',
  scraperApiKeyConfigured: false
};

export const StorageService = {
  /**
   * Salva as configurações do usuário
   */
  saveSettings: (config: CRMConfig): void => {
    try {
      localStorage.setItem(KEYS.CONFIG, JSON.stringify(config));
    } catch (e) {
      console.error('Erro ao salvar configurações:', e);
    }
  },

  /**
   * Recupera as configurações salvas ou retorna os padrões
   */
  getSettings: (): CRMConfig => {
    try {
      const saved = localStorage.getItem(KEYS.CONFIG);
      if (!saved) return DEFAULT_CONFIG;

      const parsed = JSON.parse(saved);
      
      // Merge com defaults para garantir que novos campos existam
      return {
        ...DEFAULT_CONFIG,
        ...parsed,
        useProxy: !!parsed.useProxy,
        wrapInBody: !!parsed.wrapInBody,
        simplifiedPayload: !!parsed.simplifiedPayload
      };
    } catch (e) {
      console.error('Erro ao carregar configurações:', e);
      return DEFAULT_CONFIG;
    }
  },

  /**
   * Salva o histórico de buscas
   */
  saveHistory: (history: SearchHistoryItem[]): void => {
    try {
      localStorage.setItem(KEYS.HISTORY, JSON.stringify(history));
    } catch (e) {
      console.error('Erro ao salvar histórico:', e);
    }
  },

  /**
   * Recupera o histórico de buscas
   */
  getHistory: (): SearchHistoryItem[] => {
    try {
      const saved = localStorage.getItem(KEYS.HISTORY);
      return saved ? JSON.parse(saved) : [];
    } catch (e) {
      return [];
    }
  },

  /**
   * Retorna apenas o item mais recente do histórico
   */
  getLastSearch: (): SearchHistoryItem | null => {
    const history = StorageService.getHistory();
    return history.length > 0 ? history[0] : null;
  },

  /**
   * Adiciona um item ao histórico (limitado aos últimos 20 para economizar espaço)
   */
  addToHistory: (item: SearchHistoryItem): void => {
    const current = StorageService.getHistory();
    
    // Evita duplicatas exatas no topo (mesma query e local)
    if (current.length > 0 && 
        current[0].query.toLowerCase() === item.query.toLowerCase() && 
        current[0].location.toLowerCase() === item.location.toLowerCase()) {
        
        // Se for a mesma busca, atualiza os leads e o timestamp
        current[0] = item;
        StorageService.saveHistory(current);
        return;
    }

    // Mantém apenas os últimos 20 registros para não estourar o LocalStorage
    // já que agora salvamos o JSON completo dos leads
    const updated = [item, ...current].slice(0, 20);
    StorageService.saveHistory(updated);
  },

  /**
   * Limpa o histórico
   */
  clearHistory: (): void => {
    localStorage.removeItem(KEYS.HISTORY);
  }
};
