(() => {
	'use strict'

	XF.Shoutbox = XF.Element.newHandler({
		init: function () {
			this.pollUrl = this.target.dataset.pollUrl || '/shoutbox/poll'
			this.sendUrl = this.target.dataset.sendUrl || '/shoutbox/sendmessage'
			this.olderUrl = this.target.dataset.olderUrl || '/shoutbox/older'
			this.entries = this.target.querySelector('.shoutbox-entries')
			this.input = this.target.querySelector('#shoutbox_input_message')
			this.sendButton = this.target.querySelector('#shoutbox_send_msg_button')
			this.messageBox = this.target.querySelector('.shoutbox-messages-box')
			this.lastId = this.getLastIdFromDom()
			this.loadingOlder = false
			this.hasMoreOlder = true
			this.generation = 0
			this.stopped = false
			this.pollInFlight = false
			this.initialLoadComplete = false
			this.sendInFlight = false
			this.lastSentText = null
			this.lastSentAt = 0
			this._shoutboxHeightHandle = createShoutboxHeightHandle(this)

			if (this.input) {
				this.input.addEventListener('keypress', (e) => {
					if (e.key === 'Enter') {
						e.preventDefault()
						this.sendMessage()
					}
				})
			}

			if (this.sendButton) {
				this.sendButton.addEventListener('click', (e) => {
					e.preventDefault()
					this.sendMessage()
				})
			}

			XF.on(document, 'ajax:complete', (e) => {
				const data = e.data
				if (data && data.shoutboxRefresh) {
					setTimeout(() => this.loadLatest(), 150)
				}
			})

			document.addEventListener('visibilitychange', () => {
				if (document.hidden) {
					this.stopped = true
				} else {
					this.stopped = false
					this.poll()
				}
			})

			if (this.messageBox) {
				this.messageBox.addEventListener('scroll', () => {
					// When scrolled to top, fetch older history.
					if (this.messageBox.scrollTop <= 0) {
						this.loadOlder()
					}
				})
			}

			this.initialAutoScroll()
      this.poll()
			this._pollInterval = setInterval(() => { if (!this.stopped) { this.poll() } }, 3000)
		},

		getFirstIdFromDom: function () {
			if (!this.entries) { return 0 }

			const first = this.entries.querySelector('li[data-messageid]')

			return first ? parseInt(first.dataset.messageid || '0', 10) : 0
		},

		loadOlder: function () {
			if (this.loadingOlder) { return }
			if (!this.hasMoreOlder) { return }
			if (!this.entries || !this.messageBox) { return }

			const beforeId = this.getFirstIdFromDom()
			if (!beforeId) { return }

			this.loadingOlder = true

			const prevScrollTop = this.messageBox.scrollTop
			const prevScrollHeight = this.messageBox.scrollHeight

			XF.ajax('GET', this.olderUrl, { before_id: beforeId, limit: 50 }, (data) => {
				if (!data) { return }

				const html = (this.getHtmlFromResponse(data) || '').trim()
				if (!html) {
					this.hasMoreOlder = false
					return
				}

				const tempDiv = document.createElement('div')
				tempDiv.innerHTML = html

				// Prepend nodes in their existing order.
				const nodes = Array.from(tempDiv.childNodes)
				for (let i = nodes.length - 1; i >= 0; i--) {
					this.entries.insertBefore(nodes[i], this.entries.firstChild)
				}

				XF.activate(this.entries)
				XF.DynamicDate.refresh(this.entries)

				// Keep our scroll position after prepending
				const newScrollHeight = this.messageBox.scrollHeight
				const delta = newScrollHeight - prevScrollHeight
				this.messageBox.scrollTop = prevScrollTop + delta

				if (data.has_more === false) {
					this.hasMoreOlder = false
				}
			}, { skipDefault: true, global: false })
				.finally(() => {
					this.loadingOlder = false
				})
		},

		initialAutoScroll: function () {
			if (this.initialLoadComplete) {
				return
			}

			// Defer until layout is ready
			setTimeout(() => {
				this.scrollToBottom()
				this.initialLoadComplete = true
			}, 0)
		},

		isNearBottom: function (thresholdPx = 80) {
			if (!this.messageBox) {
				return true
			}

			const el = this.messageBox
			const distance = el.scrollHeight - (el.scrollTop + el.clientHeight)
			return distance <= thresholdPx
		},

		getHtmlFromResponse: function (data) {
			if (!data) { return '' }
			if (typeof data.messages_html === 'string') { return data.messages_html } 			// Server returns `messages_html` since XF ajax reserves 'html'
			if (data.html == null) { return '' }

			const html = data.html
			if (typeof html === 'string') { return html }

			// XF ajax responses wrap HTML as an object like { content: "..." }
			if (typeof html === 'object') {
				if (typeof html.content === 'string') {
					return html.content
				}
				if (typeof html.html === 'string') {
					return html.html
				}
			}

			return ''
		},

		getLastIdFromDom: function () {
			if (!this.entries) { return 0 }

			const last = this.entries.querySelector('li[data-messageid]:last-child')

			return last ? parseInt(last.dataset.messageid || '0', 10) : 0
		},

		scrollToBottom: function () {
			if (this.messageBox) {
				this.messageBox.scrollTop = this.messageBox.scrollHeight
			}
		},

		loadLatest: function () {
			// if (this.pollInFlight) { return }
			this.pollInFlight = true

			XF.ajax('GET', this.pollUrl, { load: 1, generation: this.generation }, (data) => {
				if (!data || !this.entries) { return }

				const wasNearBottom = this.isNearBottom()
				const previousLastId = this.lastId
				const html = (this.getHtmlFromResponse(data) || '').trim()

				this.entries.innerHTML = html
				const newLastId = data.last_id || this.getLastIdFromDom()
				this.lastId = newLastId
				if (data.generation) {
					this.generation = data.generation
				}
				XF.activate(this.entries)
				XF.DynamicDate.refresh(this.entries)

				const hasNew = (newLastId > previousLastId)

				if (hasNew && wasNearBottom) {
					this.scrollToBottom()
				}
			}, { skipDefault: true, global: false })
				.finally(() => { this.pollInFlight = false })
		},

		poll: function () {
			if (this.stopped || this.pollInFlight) { return }

			this.pollInFlight = true
			const wasNearBottom = this.isNearBottom()
			const previousLastId = this.lastId

			XF.ajax('GET', this.pollUrl, { last_id: this.lastId, generation: this.generation }, (data) => {
				if (!data) { return }

				const html = (this.getHtmlFromResponse(data) || '').trim()
				const newLastId = data.last_id || previousLastId
				const hasNew = (newLastId > previousLastId)

				if (data.mode === 'replace') {
					this.entries.innerHTML = html
					this.lastId = newLastId || this.getLastIdFromDom()

					if (data.generation) {
						this.generation = data.generation
					}

					XF.activate(this.entries)
					XF.DynamicDate.refresh(this.entries)

					// If this "replace" contained new messages, scroll only if the user
					// was already at the bottom.
					if (hasNew && wasNearBottom) {
						this.scrollToBottom()
					}

					return
				}

				if (html && this.entries) {
					const tempDiv = document.createElement('div')
					tempDiv.innerHTML = html

					while (tempDiv.firstChild) {
						this.entries.appendChild(tempDiv.firstChild)

          }

          this.lastId = newLastId || this.lastId

          if (data.generation) {
						this.generation = data.generation
					}

          XF.activate(this.entries)
					XF.DynamicDate.refresh(this.entries)

					if (hasNew && wasNearBottom) {
						this.scrollToBottom()
					}
				}
			}, { skipDefault: true, global: false })
				.finally(() => {
					this.pollInFlight = false
				})
		},

		sendMessage: function () {
			if (!this.input) { return }
			if (this.sendInFlight) { return }
			if (this.input.disabled) { return }
			const value = (this.input.value || '').trim()
			if (!value) { return }

			const now = Date.now()
			if (this.lastSentText !== null
				&& value === this.lastSentText
				&& (now - this.lastSentAt) < 3000
			) {
				return
			}

			const clientNonce = now.toString(36) + Math.random().toString(36).slice(2, 10)

			this.sendInFlight = true
			this.input.disabled = true
			this.input.classList.add('disabled')

			if (this.sendButton) {
				this.sendButton.classList.add('disabled')
				this.sendButton.setAttribute('aria-disabled', 'true')
			}

			const formData = new FormData()
			formData.append('message', value)
			formData.append('client_nonce', clientNonce)

			XF.ajax('POST', this.sendUrl, formData, () => {
				this.lastSentText = value
				this.lastSentAt = now
			})
				.finally(() => {
					this.sendInFlight = false
					this.input.disabled = false
					this.input.classList.remove('disabled')

					if (this.sendButton) {
						this.sendButton.classList.remove('disabled')
						this.sendButton.removeAttribute('aria-disabled')
					}
					this.input.value = ''
				})
		}
	})

	XF.ShoutboxHandleEmojis = XF.Element.newHandler({
		init: function () {
			const menu = this.target
			const container = menu.closest('.shoutbox_box_main') || document
			const inputShoutField = container.querySelector('#shoutbox_input_message')
			const trigger = menu.previousElementSibling
			let loaded = false
			let menuScroll = null
			let scrollTop = 0
			let flashTimeout = null
			let logTimeout = null
			let searchTimer = null

			function insertAtCursor (text) {
				if (!inputShoutField) { return }
				const toInsert = (text || '').trim()
				if (!toInsert) { return }

				const insertText = toInsert + ' '
				const start = (typeof inputShoutField.selectionStart === 'number') ? inputShoutField.selectionStart : (inputShoutField.value || '').length
				const end = (typeof inputShoutField.selectionEnd === 'number') ? inputShoutField.selectionEnd : (inputShoutField.value || '').length
				const current = inputShoutField.value || ''

				inputShoutField.value = current.slice(0, start) + insertText + current.slice(end)

				try {
					const pos = start + insertText.length
					inputShoutField.setSelectionRange(pos, pos)
				} catch (e) { }

				inputShoutField.focus()
				inputShoutField.dispatchEvent(new Event('input', { bubbles: true }))
			}

			function handleEmojiClick (e) {
				const emojiLink = e.target.closest('.js-emoji')
				if (!emojiLink || !menu.contains(emojiLink) || !inputShoutField) { return }
				if (emojiLink.querySelector('.smilie--lazyLoad')) { return }

				e.preventDefault()
				e.stopPropagation()
				insertAtCursor(emojiLink.dataset.shortname)

				const insertRow = menu.querySelector('.js-emojiInsertedRow')
				if (insertRow) {
					insertRow.querySelector('.js-emojiInsert').innerHTML = emojiLink.innerHTML
					insertRow.style.display = ''
					insertRow.classList.add('is-active')
					clearTimeout(flashTimeout)

					flashTimeout = setTimeout(() => {
						insertRow.classList.remove('is-active')
						insertRow.style.display = 'none'
					}, 1500)
				}

				clearTimeout(logTimeout)
				logTimeout = setTimeout(() => XF.logRecentEmojiUsage(emojiLink.dataset.shortname), 1500)
			}

			function lazyLoadEmoji (toLoad) {
				if (!toLoad || toLoad.tagName !== 'SPAN') { return }
				if (!toLoad.classList || !toLoad.classList.contains('smilie--lazyLoad')) { return }

				const image = XF.createElement('img', {
					className: toLoad.getAttribute('class').replace(/(\s|^)smilie--lazyLoad(\s|$)/, ' '),
					alt: toLoad.getAttribute('data-alt'),
					title: toLoad.getAttribute('title'),
					src: toLoad.getAttribute('data-src'),
					dataset: { shortname: toLoad.dataset.shortname }
				})

				const replace = () => {
					window.requestAnimationFrame(() => {
						toLoad.outerHTML = image.outerHTML
					})
				}

				if (image.complete) {
					XF.on(image, 'load', replace)
				} else {
					replace()
				}
			}

			function setupLazyLoad () {
				if (!menuScroll) { return }

				if (typeof IntersectionObserver === 'undefined') {
					menu.querySelectorAll('span.smilie--lazyLoad').forEach(lazyLoadEmoji)

					return
				}

				const observer = new IntersectionObserver((changes, obs) => {
					for (const entry of changes) {
						if (!entry.isIntersecting) { continue }
						lazyLoadEmoji(entry.target)
						obs.unobserve(entry.target)
					}
				}, {
					root: menuScroll,
					rootMargin: '0px 0px 200px 0px'
				})

				menuScroll.querySelectorAll('span.smilie--lazyLoad').forEach(smilie => observer.observe(smilie))
			}

			function performSearch () {
				const emojiSearch = menu.querySelector('.js-emojiSearch')
				if (!emojiSearch) { return }

				const fullList = menu.querySelector('.js-emojiFullList')
				const searchResults = menu.querySelector('.js-emojiSearchResults')
				const value = emojiSearch.value

				clearTimeout(searchTimer)

				if (!value || value.length < 2) {
					if (searchResults) { searchResults.style.display = 'none' }
					if (fullList) { fullList.style.display = '' }

					return
				}

				searchTimer = setTimeout(() => {
					const url = XF.canonicalizeUrl('index.php?editor/smilies-emoji/search')
					XF.ajax('GET', url, { q: value }, data => {
						if (!data.html) { return }

						XF.setupHtmlInsert(data.html, html => {
							if (fullList) { fullList.style.display = 'none' }

							searchResults.innerHTML = html.outerHTML
							searchResults.style.display = ''
							searchResults.querySelectorAll('span.smilie--lazyLoad').forEach(lazyLoadEmoji)
						})
					})
				}, 300)
			}

			function updateRecentEmoji () {
				if (!menuScroll) { return }

				const recent = XF.getRecentEmojiUsage()
				if (!recent) { return }

				const recentHeader = menuScroll.querySelector('.js-recentHeader')
				const recentBlock = menuScroll.querySelector('.js-recentBlock')
				if (!recentBlock) { return }

				const recentList = recentBlock.querySelector('.js-recentList')
				const emojiLists = menuScroll.querySelectorAll('.js-emojiList')

				const items = []
				for (const shortname of recent) {
					emojiLists.forEach(list => {
						const emoji = list.querySelector('.js-emoji[data-shortname="' + shortname + '"]')

            if (emoji) {
							items.push(emoji.closest('li').cloneNode(true))
						}
					})
				}

				recentList.innerHTML = ''
				items.forEach(li => recentList.appendChild(li))

				if (recentBlock.classList.contains('is-hidden')) {
					recentBlock.classList.remove('is-hidden')
					recentBlock.style.display = ''

					if (recentHeader) { recentHeader.classList.remove('is-hidden') }
				}
			}

			function onMenuLoaded () {
				if (loaded) { return }

				menuScroll = menu.querySelector('.menu-scroller')
				if (!menuScroll) { return }

				loaded = true

				const insertRow = menu.querySelector('.js-emojiInsertedRow')
				if (insertRow) { insertRow.style.display = 'none' }

				setupLazyLoad()

				XF.on(menu, 'click', handleEmojiClick)

				const emojiSearch = menu.querySelector('.js-emojiSearch')
				if (emojiSearch) { XF.on(emojiSearch, 'input', performSearch) }

				const closer = menu.querySelector('.js-emojiCloser')
				if (closer) {
					XF.on(closer, 'click', () => {
						if (inputShoutField) { inputShoutField.focus() }
					})
				}

				XF.on(document, 'recent-emoji:logged', updateRecentEmoji)

				XF.on(menu, 'menu:closed', () => {
					if (menuScroll) { scrollTop = menuScroll.scrollTop }
				})
			}

			if (trigger) {
				XF.on(trigger, 'menu:complete', () => {
					onMenuLoaded()
					if (menuScroll) { menuScroll.scroll({ top: scrollTop }) }
				})
			}
			XF.on(menu, 'menu:opened', () => {
				onMenuLoaded()
				if (menuScroll) { menuScroll.scroll({ top: scrollTop }) }
			})
		}
	})

	XF.Element.register('shoutbox', 'XF.Shoutbox')
	XF.Element.register('shoutbox-emoji-button', 'XF.ShoutboxHandleEmojis')

	function createShoutboxHeightHandle(parent) {
		const MIN_HEIGHT = 150
		const MAX_HEIGHT = 635

		let isResizing = false
		let initialPageY = 0
		let startHeight = 0

		const box = parent.messageBox || parent.entries || parent.target
		if (!box) return null

		// Apply saved height if present
		const saved = parseInt(localStorage.getItem('shoutboxHeight'), 10)
		if (!Number.isNaN(saved)) {
			box.style.height = saved + 'px'
		}

		const button = (parent.target && parent.target.querySelector)
			? parent.target.querySelector('#shoutbox_resize_button')
			: document.getElementById('shoutbox_resize_button')
		if (!button) return null

		const onMouseMove = (e) => {
			if (!isResizing) return

			let delta = e.pageY - initialPageY
			let newH = startHeight + delta

			if (newH < MIN_HEIGHT) newH = MIN_HEIGHT
			if (newH > MAX_HEIGHT) newH = MAX_HEIGHT

			box.style.height = newH + 'px'

			box.scrollTop = box.scrollHeight
		}

		const onMouseUp = () => {
			if (!isResizing) return

			isResizing = false
			document.removeEventListener('mousemove', onMouseMove)
			document.removeEventListener('mouseup', onMouseUp)

			const shoutboxHeight = parseInt(box.style.height, 10)
			if (!Number.isNaN(shoutboxHeight)) {
				localStorage.setItem('shoutboxHeight', shoutboxHeight)
			}
		}

		button.addEventListener('mousedown', (e) => {
			e.preventDefault()

			isResizing = true
			startHeight = box.clientHeight
			initialPageY = e.pageY

			document.addEventListener('mousemove', onMouseMove)
			document.addEventListener('mouseup', onMouseUp)
		})
	}
})()