
export interface CRMConfig {
  baseUrl: string;
  token: string;
  tenantName?: string;
  useProxy?: boolean;
  wrapInBody?: boolean;
  simplifiedPayload?: boolean;
  // Gemini 2.0 Flash is generally available and stable.
  // Gemini 2.5 series is required for advanced Google Maps grounding if available.
  selectedModel?: 'gemini-2.0-flash' | 'gemini-2.5-flash' | 'gemini-2.0-flash-lite-preview-02-05';
}

export interface SearchHistoryItem {
  id: string;
  query: string;
  location: string;
  tag: string;
  timestamp: string;
  resultsCount: number;
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
