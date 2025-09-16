/**
 * ================================================================================
 * JEST SETUP - Configuração inicial para testes JavaScript
 * Mocks globais, helpers e configurações de ambiente
 * ================================================================================
 */

// Polyfills para jsdom
import 'jest-canvas-mock';

// Mock do Chart.js
global.Chart = {
  register: jest.fn(),
  defaults: {
    global: {
      responsive: true,
      maintainAspectRatio: false
    }
  },
  Chart: jest.fn().mockImplementation(() => ({
    update: jest.fn(),
    destroy: jest.fn(),
    render: jest.fn(),
    clear: jest.fn(),
    data: {
      labels: [],
      datasets: []
    },
    options: {}
  }))
};

// Mock do WebSocket
global.WebSocket = jest.fn().mockImplementation(() => ({
  close: jest.fn(),
  send: jest.fn(),
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  readyState: 1,
  onopen: null,
  onmessage: null,
  onerror: null,
  onclose: null
}));

// Mock do XMLHttpRequest
global.XMLHttpRequest = jest.fn().mockImplementation(() => ({
  open: jest.fn(),
  send: jest.fn(),
  setRequestHeader: jest.fn(),
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
  abort: jest.fn(),
  readyState: 4,
  status: 200,
  statusText: 'OK',
  responseText: '{"success": true}',
  response: '{"success": true}',
  onreadystatechange: null
}));

// Mock do fetch
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    status: 200,
    statusText: 'OK',
    headers: new Map(),
    json: () => Promise.resolve({ success: true }),
    text: () => Promise.resolve('{"success": true}')
  })
);

// Mock do localStorage
const localStorageMock = {
  getItem: jest.fn((key) => {
    return localStorageMock.store[key] || null;
  }),
  setItem: jest.fn((key, value) => {
    localStorageMock.store[key] = value.toString();
  }),
  removeItem: jest.fn((key) => {
    delete localStorageMock.store[key];
  }),
  clear: jest.fn(() => {
    localStorageMock.store = {};
  }),
  store: {}
};

global.localStorage = localStorageMock;

// Mock do sessionStorage
const sessionStorageMock = {
  getItem: jest.fn((key) => {
    return sessionStorageMock.store[key] || null;
  }),
  setItem: jest.fn((key, value) => {
    sessionStorageMock.store[key] = value.toString();
  }),
  removeItem: jest.fn((key) => {
    delete sessionStorageMock.store[key];
  }),
  clear: jest.fn(() => {
    sessionStorageMock.store = {};
  }),
  store: {}
};

global.sessionStorage = sessionStorageMock;

// Mock do Sortable (drag and drop)
global.Sortable = {
  create: jest.fn().mockImplementation(() => ({
    destroy: jest.fn(),
    option: jest.fn()
  }))
};

// Mock do window.location
delete window.location;
window.location = {
  href: 'http://localhost:8000/dashboard',
  origin: 'http://localhost:8000',
  protocol: 'http:',
  host: 'localhost:8000',
  hostname: 'localhost',
  port: '8000',
  pathname: '/dashboard',
  search: '',
  hash: '',
  reload: jest.fn(),
  assign: jest.fn()
};

// Mock do console para capturar logs em testes
const originalConsole = global.console;

global.console = {
  ...originalConsole,
  log: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
  info: jest.fn(),
  debug: jest.fn()
};

// Helper para restaurar console original quando necessário
global.restoreConsole = () => {
  global.console = originalConsole;
};

// Mock do Intersection Observer
global.IntersectionObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn()
}));

// Mock do ResizeObserver
global.ResizeObserver = jest.fn().mockImplementation(() => ({
  observe: jest.fn(),
  unobserve: jest.fn(),
  disconnect: jest.fn()
}));

// Helper para criar elementos DOM de teste
global.createTestElement = (tag = 'div', options = {}) => {
  const element = document.createElement(tag);
  
  if (options.id) element.id = options.id;
  if (options.className) element.className = options.className;
  if (options.innerHTML) element.innerHTML = options.innerHTML;
  if (options.attributes) {
    Object.entries(options.attributes).forEach(([key, value]) => {
      element.setAttribute(key, value);
    });
  }
  
  if (options.appendTo) {
    options.appendTo.appendChild(element);
  } else {
    document.body.appendChild(element);
  }
  
  return element;
};

// Helper para simular eventos
global.simulateEvent = (element, eventType, options = {}) => {
  const event = new Event(eventType, {
    bubbles: true,
    cancelable: true,
    ...options
  });
  
  // Adicionar propriedades específicas do evento
  if (options.detail) event.detail = options.detail;
  if (options.clientX) event.clientX = options.clientX;
  if (options.clientY) event.clientY = options.clientY;
  
  element.dispatchEvent(event);
  return event;
};

// Helper para aguardar próximo tick
global.nextTick = () => new Promise(resolve => setTimeout(resolve, 0));

// Helper para aguardar elemento aparecer no DOM
global.waitForElement = (selector, timeout = 5000) => {
  return new Promise((resolve, reject) => {
    const startTime = Date.now();
    
    const check = () => {
      const element = document.querySelector(selector);
      
      if (element) {
        resolve(element);
      } else if (Date.now() - startTime > timeout) {
        reject(new Error(`Element ${selector} not found within ${timeout}ms`));
      } else {
        setTimeout(check, 50);
      }
    };
    
    check();
  });
};

// Helper para aguardar requisição AJAX
global.waitForAjax = (timeout = 5000) => {
  return new Promise((resolve) => {
    setTimeout(resolve, 100); // Simular delay de rede
  });
};

// Helper para mockar dados de teste
global.mockDashboardData = {
  stats: {
    total_dis: 150,
    total_adicoes: 450,
    valor_total_usd: 2500000,
    valor_total_brl: 12500000,
    total_impostos: 1875000,
    ticket_medio_usd: 16666.67,
    periodo: '6months',
    ultima_atualizacao: '2024-01-15T10:30:00Z'
  },
  
  chartData: {
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    datasets: [{
      label: 'Valor USD',
      data: [400000, 450000, 380000, 520000, 480000, 550000],
      backgroundColor: '#3b82f6',
      borderColor: '#1e40af'
    }]
  },
  
  searchResults: {
    data: [
      {
        numero_di: 'TEST001',
        data_registro: '2024-01-15',
        importador_nome: 'Equiplex Industrial Ltda',
        valor_total_usd: 25000,
        valor_total_brl: 125000,
        status: 'concluida'
      }
    ],
    pagination: {
      current_page: 1,
      total_pages: 1,
      total_records: 1,
      records_per_page: 25
    }
  }
};

// Setup de limpeza após cada teste
afterEach(() => {
  // Limpar DOM
  document.body.innerHTML = '';
  
  // Limpar mocks
  jest.clearAllMocks();
  
  // Limpar storage
  localStorage.clear();
  sessionStorage.clear();
  
  // Restaurar fetch mock
  fetch.mockClear();
  
  // Limpar timers
  jest.clearAllTimers();
});

// Setup global para todos os testes
beforeAll(() => {
  // Configurar viewport
  Object.defineProperty(window, 'innerWidth', {
    writable: true,
    configurable: true,
    value: 1024
  });
  
  Object.defineProperty(window, 'innerHeight', {
    writable: true,
    configurable: true,
    value: 768
  });
  
  // Adicionar meta viewport
  const viewport = document.createElement('meta');
  viewport.name = 'viewport';
  viewport.content = 'width=device-width, initial-scale=1';
  document.head.appendChild(viewport);
});

// Matcher customizado para verificar classe CSS
expect.extend({
  toHaveClass(received, className) {
    const pass = received.classList.contains(className);
    return {
      message: () =>
        pass
          ? `expected ${received} not to have class ${className}`
          : `expected ${received} to have class ${className}`,
      pass
    };
  },
  
  toBeVisible(received) {
    const style = window.getComputedStyle(received);
    const pass = style.display !== 'none' && style.visibility !== 'hidden';
    return {
      message: () =>
        pass
          ? `expected element to be hidden`
          : `expected element to be visible`,
      pass
    };
  }
});

console.log('✅ Jest setup concluído - Ambiente de testes JavaScript pronto');