/**
 * state.js - Estado Global da Aplicação
 */

var API_BASE = (typeof window.API_BASE_URL !== 'undefined' ? window.API_BASE_URL : 'api/');

// Estado centralizado
var AppState = {
    user: null,
    tenant: null,
    tokenUsage: null,
    impersonating: false,
    impersonatingTenantName: '',
    platformCompanyName: null,
    config: null,
    activeTab: 'dashboard',
    userCoords: null,
    userLocationName: '',
    locStatus: 'idle', 
    leads: [],
    searchId: null,
    history: [],
    folders: [],
    visibleCount: 12,
    currentSearch: { 
        query: '', 
        location: '', 
        tag: '', 
        folderId: null, 
        folderName: '' 
    },
    localData: {
        municipios: [],
        cnaes: [],
        loaded: false
    }
};

// Constantes
var THEME_STORAGE_KEY = 'mapsprospector-theme';