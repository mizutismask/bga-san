import { Game } from '../Game'
import { ImmediateActionArgs, SanGamedatas, SanPlayer } from '../types'

export class ImmediateAction {
	private selectableMarkers: HTMLElement[] = []

	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, SanGamedatas>
	) {}

	onEnteringState(
		args: ImmediateActionArgs,
		isCurrentPlayerActive: boolean
	) {
		if (!isCurrentPlayerActive) return
		if (args.corruptionSlots.length) {
			this.bga.statusBar.setTitle(_('${you} must select a card from your hand and choose where to corrupt it'))
			const stocks = Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)
			for (const stock of stocks) {
				stock.setSelectionMode('single')
				stock.onSelectionChange = (selection) => {
					if (selection.length) {
						for (const other of stocks) {
							if (other !== stock) other.unselectAll()
						}
					}
				}
			}
			for (const marker of document.querySelectorAll<HTMLElement>(
				`#corruption-panel-${this.game.getPlayerId()} .corruption-marker`
			)) {
				const boardSlot = Math.floor(Number(marker.id.split('-').pop()) / 10)
				const slot = Number(this.game.getCurrentPlayer().playerNo) === 1 ? boardSlot : 7 - boardSlot
				if (marker.textContent || !args.corruptionSlots.includes(slot)) continue
				marker.classList.add('selectable')
				marker.onclick = () => {
					if (marker.textContent || !this.bga.players.isCurrentPlayerActive()) return
					const card = stocks.flatMap((stock) => stock.getSelection())[0]
					if (!card) {
						this.bga.dialogs.showMessage(_('Select a card from your hand'), 'error')
						return
					}
					this.game.takeAction('actCorrupt', { cardId: card.id, slot })
				}
				this.selectableMarkers.push(marker)
			}
		}
		if (args.remainingDestroysFromHand) {
			this.bga.statusBar.setTitle(_('${you} can select up to ${remainingDestroysFromHand} card(s) from your hand to destroy'), args)
			const stocks = Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)
			const getSelectedCards = () => stocks.flatMap((stock) => stock.getSelection())
			const isValidSelection = () => {
				const count = getSelectedCards().length
				return count > 0 && count <= args.remainingDestroysFromHand
			}
			this.bga.statusBar.addActionButton(_('Destroy selected cards'), () => {
				if (isValidSelection()) {
					this.game.takeAction('actDestroy', { cardIds: getSelectedCards().map((card) => card.id).join(',') })
				}
			}, {
				id: 'buttonDestroyCards',
				color: 'primary'
			})
			const updateDestroyButton = () => {
				document.getElementById('buttonDestroyCards')?.classList.toggle('disabled', !isValidSelection())
			}
			for (const stock of stocks) {
				stock.onSelectionChange = updateDestroyButton
				stock.setSelectionMode('multiple')
			}
			updateDestroyButton()
			this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'alert'
			})
		}
	}

	onLeavingState() {
		for (const marker of this.selectableMarkers) {
			marker.onclick = null
			marker.classList.remove('selectable')
		}
		this.selectableMarkers = []
		for (const stock of Object.values(this.game.playerTables[this.game.getPlayerId()]?.handStocks ?? {})) {
			stock.onSelectionChange = undefined
			stock.setSelectionMode('none')
		}
	}
}
