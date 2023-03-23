// https://vitepress.dev/v3/guide/custom-theme
import { h } from 'vue'
import Theme from 'vitepress/theme'
import './style.css'

export default {
  ...Theme,
  Layout: () => {
    return h(Theme.Layout, null, {
      // https://vitepress.dev/v3/guide/extending-default-theme#layout-slots
    })
  },
  enhanceApp({ app, router, siteData }) {
    // ...
  }
}
