
export interface CRMConfig {
  baseUrl: string;
  token: string;
  tenantName?: string;
  useProxy?: boolean;
  wrapInBody?: boolean;
  simplifiedPayload?: boolean;
  scraperApiKey?: string;
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
  address: string;
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

export interface AppUser {
  id: string | number;
  name: string;
  email: string;
  tenantId: string | number;
  profile: string;
}

export interface AppTenant {
  id: string | number;
  name: string;
}
