/**
 * ================================================================================
 * CONFIGURAÇÃO JEST - Testes JavaScript Frontend
 * Testes unitários para componentes, funções e interações do dashboard
 * ================================================================================
 */

module.exports = {
  // Ambiente de teste
  testEnvironment: 'jsdom',
  
  // Diretórios de teste
  testMatch: [
    '<rootDir>/Unit/JavaScript/**/*.test.js',
    '<rootDir>/Integration/Frontend/**/*.test.js'
  ],
  
  // Setup files
  setupFilesAfterEnv: [
    '<rootDir>/setup/jest.setup.js'
  ],
  
  // Transformações
  transform: {
    '^.+\\.js$': 'babel-jest'
  },
  
  // Module mapping para assets
  moduleNameMapping: {
    '^@/(.*)$': '<rootDir>/../assets/js/$1',
    '^@css/(.*)$': '<rootDir>/../assets/css/$1'
  },
  
  // Mock de arquivos estáticos
  moduleFileExtensions: ['js', 'json', 'css'],
  
  // Coverage configuration
  collectCoverage: true,
  collectCoverageFrom: [
    '../assets/js/**/*.js',
    '!../assets/js/**/*.min.js',
    '!../assets/js/vendor/**/*'
  ],
  
  coverageDirectory: 'reports/coverage/js',
  coverageReporters: ['html', 'text', 'text-summary', 'lcov'],
  
  coverageThreshold: {
    global: {
      branches: 80,
      functions: 85,
      lines: 85,
      statements: 85
    }
  },
  
  // Timeouts
  testTimeout: 10000,
  
  // Verbose output
  verbose: true,
  
  // Clear mocks automaticamente
  clearMocks: true,
  
  // Mock timers
  fakeTimers: {
    enableGlobally: false
  },
  
  // Error on deprecated features
  errorOnDeprecated: true,
  
  // Globals
  globals: {
    'window': {},
    'document': {},
    'Chart': {},
    'Sortable': {},
    'XMLHttpRequest': {},
    'WebSocket': {}
  },
  
  // Test results processor
  testResultsProcessor: '<rootDir>/processors/jest-results.js',
  
  // Reporters
  reporters: [
    'default',
    ['jest-html-reporters', {
      publicPath: './reports/jest',
      filename: 'report.html',
      expand: true,
      hideIcon: false
    }]
  ],
  
  // Watch mode options
  watchman: false,
  
  // Ignore patterns
  testPathIgnorePatterns: [
    '/node_modules/',
    '/vendor/',
    '/temp/'
  ],
  
  // Module paths
  modulePaths: [
    '<rootDir>/../assets/js'
  ]
};