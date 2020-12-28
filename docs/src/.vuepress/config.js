const { description } = require('../../package')

module.exports = {
  /**
   * Ref：https://v1.vuepress.vuejs.org/config/#title
   */
  title: 'Lumen Laravel MongoDB',
  /**
   * Ref：https://v1.vuepress.vuejs.org/config/#description
   */
  description: description,
  theme: 'vuepress-theme-succinct',
  globalUIComponents: [
    'ThemeManager'
  ],
  /**
   * Extra tags to be injected to the page HTML `<head>`
   *
   * ref：https://v1.vuepress.vuejs.org/config/#head
   */
  head: [
    ['meta', { name: 'theme-color', content: '#3eaf7c' }],
    ['meta', { name: 'apple-mobile-web-app-capable', content: 'yes' }],
    ['meta', { name: 'apple-mobile-web-app-status-bar-style', content: 'black' }]
  ],

  /**
   * Theme configuration, here is the default theme configuration for VuePress.
   *
   * ref：https://v1.vuepress.vuejs.org/theme/default-theme-config.html
   */
  themeConfig: {
    repo: '',
    editLinks: false,
    docsDir: '',
    editLinkText: '',
    lastUpdated: false,
    nav: [
      {
        text: 'Guide',
        link: '/guide/',
      },
      // {
      //   text: 'Config',
      //   link: '/config/'
      // },
      {
        text: 'Github',
        link: 'https://v1.vuepress.vuejs.org'
      }
    ],
    sidebar: {
      '/guide/': [
        '',
        {
          title: 'Setup',
          collapsable: true,
          children: [
            'installation',
            'test',
            'configuration'
          ]
        },
        {
          title: 'Guide',
          collapsable: true,
          children: [
            {
              title: 'CRUD Functionalities',
              collapsable: true,
              children: [
                'create-function',
                'read-function',
                'update-function',
                'delete-function'
              ]
            },
            {
              title: 'DATES',
              collapsable: true,
              children: [
                'in-between',
              ]
            },
          ]
        },
        {
          title: 'Association',
          collapsable: true,
          children: [
            'hasMany',
            'embeds-one',
            'embeds-many'
          ]
        },
        'schema',
        'extending'
      ],
    }
  },

  /**
   * Apply plugins，ref：https://v1.vuepress.vuejs.org/zh/plugin/
   */
  plugins: [
    '@vuepress/plugin-back-to-top',
    '@vuepress/plugin-medium-zoom',
  ]
}
