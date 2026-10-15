import { SanCard } from '../types'
import { CARD_TYPE_HARDWARE, CARD_TYPE_PROPAGANDA, CARD_TYPE_HACKING } from '../constants'

export class ChoicePanel {
	private closeCurrent?: () => void

	close() {
		this.closeCurrent?.()
	}

	open(card: SanCard, anchor: HTMLElement, play: (choice: number) => void) {
		this.close()
		const panel = document.createElement('div')
		panel.className = 'card-choice-panel'
		panel.setAttribute('role', 'group')
		panel.setAttribute('aria-label', _('Choose an effect'))
		const title = document.createElement('strong')
		title.textContent = _('Choose an effect')
		panel.appendChild(title)
		const hardware = Number(card.type_arg) === CARD_TYPE_HARDWARE
		const effects = [_('Gain 1 propaganda'), _('Gain 1 hacking'), _('Gain 1 corruption')]
		const labels = hardware ? effects : [
			Number(card.type_arg) === CARD_TYPE_PROPAGANDA ? _('Gain 2 propaganda') :
				Number(card.type_arg) === CARD_TYPE_HACKING ? _('Gain 2 hacking') : _('Gain 2 corruption'),
			_('Draw 2 cards')
		]
		const symbols = hardware ? [4, 3, 2] : [
			Number(card.type_arg) === CARD_TYPE_PROPAGANDA ? 4 :
				Number(card.type_arg) === CARD_TYPE_HACKING ? 3 : 2,
			10
		]
		labels.forEach((label, index) => {
			const button = document.createElement('button')
			button.type = 'button'
			button.className = 'card-choice-option'
			button.setAttribute('aria-label', label)
			button.title = label
			for (let count = 0; count < (hardware ? 1 : 2); count++) {
				const symbol = document.createElement('span')
				symbol.className = `game-symbol game-symbol-${symbols[index]}`
				symbol.setAttribute('aria-hidden', 'true')
				button.appendChild(symbol)
			}
			button.onclick = () => {
				this.close()
				play(index + 1)
			}
			panel.appendChild(button)
		})
		const cancel = document.createElement('button')
		cancel.type = 'button'
		cancel.className = 'card-choice-cancel'
		cancel.textContent = _('Cancel')
		cancel.onclick = () => this.close()
		panel.appendChild(cancel)
		document.body.appendChild(panel)
		anchor.classList.add('choice-panel-open')
		const position = () => {
			const rect = anchor.getBoundingClientRect()
			const width = panel.getBoundingClientRect().width
			panel.style.left = `${Math.max(8, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 8))}px`
			const height = panel.getBoundingClientRect().height
			const top = rect.bottom + height + 16 <= window.innerHeight ? rect.bottom + 8 : rect.top - height - 8
			panel.style.top = `${Math.max(8, Math.min(top, window.innerHeight - height - 8))}px`
		}
		position()
		const outside = (event: PointerEvent) => {
			if (!panel.contains(event.target as Node)) this.close()
		}
		const escape = (event: KeyboardEvent) => {
			if (event.key === 'Escape') this.close()
		}
		document.addEventListener('pointerdown', outside)
		document.addEventListener('keydown', escape)
		window.addEventListener('scroll', position, true)
		window.addEventListener('resize', position)
		this.closeCurrent = () => {
			panel.remove()
			anchor.classList.remove('choice-panel-open')
			document.removeEventListener('pointerdown', outside)
			document.removeEventListener('keydown', escape)
			window.removeEventListener('scroll', position, true)
			window.removeEventListener('resize', position)
			this.closeCurrent = undefined
		}
		panel.querySelector('button')?.focus({ preventScroll: true })
	}
}
