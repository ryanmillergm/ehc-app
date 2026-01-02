import grapesjs from 'grapesjs'
import presetNewsletter from 'grapesjs-preset-newsletter'
import 'grapesjs/dist/css/grapes.min.css'

window.grapesEmailBuilder = function ({
  height = '700px',
  project,
  setProject,
  setHtml,
  setCss,
}) {
  return {
    editor: null,
    fullscreen: false,
    _saveTimer: null,
    applying: false,

    init() {
      if (this.editor) return

      const starterHtml = `...`

      const loadProject = () => {
        const raw = this.project
        if (!raw) return null
        if (typeof raw === 'string') {
          try { return JSON.parse(raw) } catch { return null }
        }
        return raw
      }

      this.editor = grapesjs.init({
        container: this.$refs.canvas,
        height,
        width: 'auto',
        storageManager: false,
        panels: { defaults: [] },
        plugins: [presetNewsletter],
        pluginsOpts: { 'grapesjs-preset-newsletter': {} },
        blockManager: { appendTo: this.$refs.blocks },
        styleManager: { appendTo: this.$refs.styles },
        layerManager: { appendTo: this.$refs.layers },
        traitManager: { appendTo: this.$refs.traits },
      })

      this.editor.on('load', () => {
        this.applying = true
        try {
          const pj = loadProject()
          if (pj && Object.keys(pj).length) {
            this.editor.loadProjectData(pj)
          } else {
            this.editor.setComponents(starterHtml)
          }
        } finally {
          this.applying = false
        }

        this._queueSave()
      })

      const saveEvents = [
        'update',
        'component:add',
        'component:remove',
        'component:update',
        'style:property:update',
      ]
      saveEvents.forEach(ev => this.editor.on(ev, () => this._queueSave()))
    },

    toggleFullscreen() {
      this.fullscreen = !this.fullscreen
      this.$nextTick(() => this.editor?.refresh())
    },

    _queueSave() {
      if (this.applying) return

      clearTimeout(this._saveTimer)
      this._saveTimer = setTimeout(() => {
        if (!this.editor || this.applying) return

        const pj = this.editor.getProjectData()
        const html = this.editor.getHtml()
        const css = this.editor.getCss()

        setProject(pj)
        setHtml(html)
        setCss(css)
      }, 1200) // slower debounce helps a lot
    },
  }
}
