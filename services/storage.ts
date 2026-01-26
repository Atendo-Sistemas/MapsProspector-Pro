
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
  selectedModel: 'gemini-2.0-flash'
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
      
      // Merge com defaults para garantir que novos campos (como selectedModel) existam
      // mesmo em configurações antigas salvas
      return {
        ...DEFAULT_CONFIG,
        ...parsed,
        // Garante booleanos
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
   * Adiciona um item ao histórico (limitado aos últimos 50)
   */
  addToHistory: (item: SearchHistoryItem): SearchHistoryItem[] => {
    const current = StorageService.getHistory();
    // Evita duplicatas exatas no topo
    if (current.length > 0 && 
        current[0].query === item.query && 
        current[0].location === item.location) {
        return current;
    }

    const updated = [item, ...current].slice(0, 50);
    StorageService.saveHistory(updated);
    return updated;
  },

  /**
   * Limpa o histórico
   */
  clearHistory: (): void => {
    localStorage.removeItem(KEYS.HISTORY);
  }
};
