let grapesjsModulePromise
let presetNewsletterPromise
let grapesCssPromise

function loadGrapesDependencies() {
  grapesjsModulePromise ||= import('grapesjs')
  presetNewsletterPromise ||= import('grapesjs-preset-newsletter')
  grapesCssPromise ||= import('grapesjs/dist/css/grapes.min.css')

  return Promise.all([grapesjsModulePromise, presetNewsletterPromise, grapesCssPromise])
    .then(([grapesModule, presetModule]) => ({
      grapesjs: grapesModule?.default ?? grapesModule,
      presetNewsletter: presetModule?.default ?? presetModule,
    }))
}


console.count('[admin.js] loaded')

function cleanupInstances() {
  for (const [root, editor] of instances.entries()) {
    if (!root?.isConnected) {
      try { editor?.destroy?.() } catch {}
      instances.delete(root)
    }
  }
}


const instances = new Map()

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

async function loadAssetsInto(editor) {
  try {
    const res = await fetch('/admin/email-assets', { credentials: 'same-origin' })
    const json = await res.json()
    const assets = Array.isArray(json?.data) ? json.data : []

    if (assets.length) {
      editor.AssetManager.add(assets)
      editor.AssetManager.render()
    }

    console.log('[GJS] assets loaded:', assets.length)
  } catch (e) {
    console.warn('[GJS] asset load failed', e)
  }
}


function findWireModelInput(form, key) {
  // match BOTH:
  // - wire:model="design_html"
  // - wire:model="data.design_html"
  const attrNames = [
    'wire:model',
    'wire:model.defer',
    'wire:model.live',
    'wire:model.lazy',
    'wire:model.blur',
  ]

  const els = form.querySelectorAll('input, textarea')
  for (const el of els) {
    for (const attr of attrNames) {
      const v = el.getAttribute(attr)
      if (!v) continue
      if (v === key || v.endsWith('.' + key)) return el
    }
  }

  return null
}

function flushOne(root, reason = 'flush') {
  const editor = instances.get(root)
  if (!editor) return

  const form = root.closest('form')
  if (!form) return

  const htmlKey = root.dataset.gjsHtmlKey || 'design_html'
  const cssKey  = root.dataset.gjsCssKey  || 'design_css'

  const html = editor.getHtml() || ''
  const css  = editor.getCss() || ''

  const htmlEl = findWireModelInput(form, htmlKey)
  const cssEl  = findWireModelInput(form, cssKey)

  const jsonKey = root.dataset.gjsJsonKey || 'design_json'
  const jsonEl  = findWireModelInput(form, jsonKey)

  const project = editor.getProjectData ? editor.getProjectData() : null
  const jsonStr = project ? JSON.stringify(project) : ''

  if (jsonEl) {
    jsonEl.value = jsonStr
    jsonEl.dispatchEvent(new Event('input', { bubbles: true }))
    jsonEl.dispatchEvent(new Event('change', { bubbles: true }))
  }

  if (htmlEl) {
    htmlEl.value = html
    htmlEl.dispatchEvent(new Event('input', { bubbles: true }))
    htmlEl.dispatchEvent(new Event('change', { bubbles: true }))
  }

  if (cssEl) {
    cssEl.value = css
    cssEl.dispatchEvent(new Event('input', { bubbles: true }))
    cssEl.dispatchEvent(new Event('change', { bubbles: true }))
  }

  console.log(`[GJS:${root.dataset.gjsKey}] ${reason}`, {
    htmlLen: html.length,
    cssLen: css.length,
    foundHtmlInput: !!htmlEl,
    foundCssInput: !!cssEl,
  })
}

async function mountOne(root) {
  if (!root || root.dataset.gjsMounted === '1') return
  root.dataset.gjsMounted = '1'

  const key = root.dataset.gjsKey || 'gjs'

  let grapesjs
  let presetNewsletter
  try {
    ({ grapesjs, presetNewsletter } = await loadGrapesDependencies())
  } catch (e) {
    console.error('[GJS] failed to load editor dependencies', e)
    root.dataset.gjsMounted = '0'
    return
  }

  const blocks = root.querySelector('[data-gjs-blocks]')
  const canvas = root.querySelector('[data-gjs-canvas]')
  const styles = root.querySelector('[data-gjs-styles]')
  const layers = root.querySelector('[data-gjs-layers]')
  const traits = root.querySelector('[data-gjs-traits]')

  const initialHtml = root.querySelector('textarea[data-gjs-initial-html]')?.value || ''
  const initialCss  = root.querySelector('textarea[data-gjs-initial-css]')?.value || ''
  const initialJson = root.querySelector('textarea[data-gjs-initial-json]')?.value || ''

  console.log(`[GJS:${key}] mount:start`)

  const start = performance.now()
  const waitForSize = () => {
    const r = canvas?.getBoundingClientRect?.()
    const ok = r && r.width > 50 && r.height > 50
    const timeout = (performance.now() - start) > 4000
    if (ok || timeout) return init()
    requestAnimationFrame(waitForSize)
  }

  const init = () => {
    if (!root.isConnected) return

    console.log(`[GJS:${key}] init`)

    const editor = grapesjs.init({
      container: canvas,
      height: '700px',
      width: 'auto',
      storageManager: false,

      plugins: [presetNewsletter],
      pluginsOpts: {
        'gjs-preset-newsletter': {},
      },

      blockManager: { appendTo: blocks },
      styleManager: { appendTo: styles },
      layerManager: { appendTo: layers },
      traitManager: { appendTo: traits },

      assetManager: {
        // show uploaded assets in the picker
        upload: '/admin/email-assets',

        // handle upload so Laravel receives files[] correctly
        uploadFile: async (e) => {
          const files = e.dataTransfer?.files || e.target?.files
          if (!files?.length) return

          const formData = new FormData()
          for (const file of files) formData.append('files[]', file)

          try {
            const res = await fetch('/admin/email-assets', {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': csrfToken() },
              body: formData,
              credentials: 'same-origin',
            })

            const json = await res.json()
            const assets = Array.isArray(json?.data) ? json.data : []

            if (assets.length) {
              editor.AssetManager.add(assets)
              editor.AssetManager.render?.() // refresh the open modal, if it’s open

              // if an image component is currently selected, set its src
              const firstSrc = assets[0]?.src
              const selected = editor.getSelected()

              if (selected?.is?.('image') && assets[0]?.src) {
                selected.addAttributes({ src: assets[0].src })
              }

              if (firstSrc && selected && selected.is && selected.is('image')) {
                selected.addAttributes({ src: firstSrc })
              }
            }

            console.log('[GJS] uploaded assets:', assets.length)
          } catch (err) {
            console.error('[GJS] upload failed', err)
          }
        },
      },
    })


    console.log(`[GJS:${key}] blocks:`, editor.BlockManager.getAll().length)

    instances.set(root, editor)

    editor.once('load', () => {
      console.log(`[GJS:${key}] load`)
      loadAssetsInto(editor)

      const jsonRaw = (initialJson || '').trim()

      if (jsonRaw.length) {
        try {
          const project = JSON.parse(jsonRaw)
          // GrapesJS v0.21+ supports this
          if (editor.loadProjectData) {
            editor.loadProjectData(project)
          } else {
            // fallback (rare/older builds)
            editor.setComponents(project?.pages?.[0]?.frames?.[0]?.component || '')
          }
        } catch (e) {
          console.warn(`[GJS:${key}] invalid design_json, falling back to html/css`, e)
          // fallback below
          const html = (initialHtml || '').trim()
          const css  = (initialCss || '').trim()
          if (html.length) {
            editor.setComponents(html)
            editor.setStyle(css)
          }
        }
      } else {
        const html = (initialHtml || '').trim()
        const css  = (initialCss || '').trim()

        if (html.length) {
          editor.setComponents(html)
          editor.setStyle(css)
        } else {
          editor.setComponents(`
            <div style="padding:24px;font-family:Arial,sans-serif">
              <h2 style="margin:0 0 10px 0">Baseline GrapesJS</h2>
              <p style="margin:0">Type here, then click Create/Save.</p>
            </div>
          `)
          editor.setStyle('')
        }
      }

      setTimeout(() => editor.refresh(), 50)
      console.log(`[GJS:${key}] ready`)
    })

  }

  requestAnimationFrame(waitForSize)
}

function mountAll() {
  cleanupInstances()
  document.querySelectorAll('[data-gjs-email-builder="1"]').forEach(mountOne)
}

document.addEventListener(
  'click',
  (e) => {
    const btn = e.target?.closest?.('[wire\\:click]')
    if (!btn) return

    const cmd = btn.getAttribute('wire:click') || ''

    // only for action things (avoid flushing on random buttons)
    if (!cmd.includes('mountAction') && !cmd.includes('call') && !cmd.includes('save')) return

    const root = document.querySelector('[data-gjs-email-builder="1"]')
    if (!root) return
    if (root.offsetParent === null) return // hidden (e.g. HTML editor mode)

    flushOne(root, 'before-action')
  },
  true // capture phase -> run BEFORE Livewire
)

// IMPORTANT: capture phase so we run before Livewire handles submit
document.addEventListener(
  'submit',
  (e) => {
    const form = e.target
    const root = form?.querySelector?.('[data-gjs-email-builder="1"]')
    if (!root) return
    if (root.offsetParent === null) return // hidden (e.g. HTML editor mode)

    flushOne(root, 'before-submit')
  },
  true
)

function setFullscreen(root, on) {
  root.classList.toggle('gjs-fullscreen', on)
  document.documentElement.classList.toggle('gjs-no-scroll', on)
  document.body.classList.toggle('gjs-no-scroll', on)

  const btn = root.querySelector('[data-gjs-fullscreen-toggle]')
  if (btn) btn.textContent = on ? 'Exit fullscreen' : 'Fullscreen'

  const editor = instances.get(root)
  // Give layout a beat to settle, then refresh GrapesJS canvas
  setTimeout(() => {
    try { editor?.refresh?.() } catch {}
  }, 60)
}

document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('[data-gjs-fullscreen-toggle]')
  if (!btn) return

  const root = btn.closest('[data-gjs-email-builder="1"]')
  if (!root) return

  e.preventDefault()

  const on = !root.classList.contains('gjs-fullscreen')
  setFullscreen(root, on)
})

document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return

  const root = document.querySelector('[data-gjs-email-builder="1"].gjs-fullscreen')
  if (!root) return

  setFullscreen(root, false)
})

document.addEventListener('livewire:init', () => {
  // Livewire v3: run after every successful commit (form updates, select changes, saves, etc.)
  Livewire.hook('commit', ({ succeed }) => {
    succeed(() => {
      mountAll()
    })
  })
})


window.addEventListener('livewire:navigating', () => {
  for (const [root, editor] of instances.entries()) {
    try { editor?.destroy?.() } catch {}
    instances.delete(root)
    try { root.dataset.gjsMounted = '0' } catch {}
  }
})

document.addEventListener('DOMContentLoaded', mountAll)
window.addEventListener('livewire:load', mountAll)
window.addEventListener('livewire:navigated', mountAll)
