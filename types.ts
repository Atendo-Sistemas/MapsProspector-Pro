
export interface CRMConfig {
  baseUrl: string;
  token: string;
  tenantName?: string;
  useProxy?: boolean;
  wrapInBody?: boolean;
  simplifiedPayload?: boolean;
  /** Chave da API de busca: só super_admin vê e edita; demais usam a da plataforma */
  scraperApiKey?: string;
  /** Indica se a chave da API de busca está configurada na plataforma (para usuários não super_admin) */
  scraperApiKeyConfigured?: boolean;
}

export interface SearchHistoryItem {
  id: string;
  query: string;
  location: string;
  tag: string;
  timestamp: string;
  resultsCount: number;
  leads?: Lead[]; // Armazena os resultados completos
}

export interface Lead {
  id: string;
  name: string;
  address?: string;
  phone?: string;
  email?: string;
  website?: string;
  mapsUri?: string;
  cnpj?: string;
  partners?: string;
  tag?: string;
  latitude?: number;
  longitude?: number;
  sources?: { title?: string; uri: string }[];
  /** Resultado bloqueado: dados sensíveis só após desbloqueio (1 token por lead) */
  locked?: boolean;
  encrypted_data?: string;
  dbId?: number;
}

export interface CRMContact {
  name: string;
  number: string;
  email?: string;
  cnpj?: string;
  tag?: string;
  extraInfo?: { name: string; value: string }[];
  commentary?: string;
  source?: string;
}

export type UserProfile = 'super_admin' | 'admin' | 'user';

export interface AppUser {
  id: string | number;
  name: string;
  email: string;
  tenantId: string | number;
  profile: UserProfile;
}

export interface AppTenant {
  id: string | number;
  name: string;
  status?: string;
  planId?: string;
  planName?: string;
}

/** Plano listado para cliente escolher (plans-public) */
export interface PlanPublicRow {
  id: string;
  name: string;
  slug: string;
  tokenLimit: number;
  priceMonthly: number;
  period: string;
}

/** Solicitação de plano (cliente solicita; super_admin confirma) */
export interface PlanRequestRow {
  id: string;
  tenantId: string;
  tenantName?: string | null;
  requestedByUserId?: string;
  requestedByName?: string | null;
  requestedByEmail?: string | null;
  planId: string;
  planName?: string | null;
  planTokenLimit?: number;
  planPrice?: number;
  status: string;
  createdAt: string;
  reviewedAt?: string | null;
}

export interface TokenUsage {
  used: number;
  limit: number;
  limitReached: boolean;
}

export interface TenantRow {
  id: string;
  name: string;
  slug: string;
  plan: string;
  planId: string;
  planTokenLimit?: number;
  status: string;
  usersCount: number;
  createdAt: string;
}

export interface PlanRow {
  id: string;
  name: string;
  slug: string;
  tokenLimit: number;
  priceMonthly?: number;
  period: string;
  status: string;
  tenantsCount?: number;
  createdAt?: string;
}
