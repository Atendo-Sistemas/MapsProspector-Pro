import { TokenUsage } from '../types';

const API_BASE = '/MapsProspector-Pro/api/';

export interface UnlockResult {
  unlocked: Record<string, {
    phone?: string;
    email?: string;
    address?: string;
    website?: string;
    cnpj?: string;
    partners?: string;
    mapsUri?: string;
    latitude?: number;
    longitude?: number;
  }>;
  tokenUsage?: TokenUsage;
}

/**
 * Desbloqueia um ou mais leads; retorna os dados sensíveis. Não debita tokens.
 */
export const unlockLeads = async (
  searchId: string,
  leadIds: string[]
): Promise<UnlockResult> => {
  const response = await fetch(API_BASE + 'unlock.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ searchId, leadIds }),
  });
  const text = await response.text();
  if (!response.ok) {
    let errorMsg = `Erro ${response.status}`;
    try {
      if (text) {
        const data = JSON.parse(text);
        errorMsg = data.error || errorMsg;
      }
    } catch {
      // ignore
    }
    throw new Error(errorMsg);
  }
  const data = JSON.parse(text || '{}');
  if (!data.success) {
    throw new Error(data.error || 'Erro ao desbloquear');
  }
  return {
    unlocked: data.data?.unlocked || {},
    tokenUsage: data.data?.tokenUsage,
  };
};
