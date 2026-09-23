import { Game } from '../Game'
import { SanGamedatas, SanPlayer, PlayerDecisionArgs } from '../types'

export class PlayerDecision {
	private selectableMarkers: HTMLElement[] = []

	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, SanGamedatas>
	) {}

	onEnteringState(args: PlayerDecisionArgs, isCurrentPlayerActive: boolean) {
		if (isCurrentPlayerActive) {
			if (args.canCorrupt) {
				this.bga.statusBar.setTitle(_('${you} can corrupt a card from the river'))
				this.game.river.setSelectionMode('single')
				const playerId = this.game.getPlayerId()
				for (const marker of document.querySelectorAll<HTMLElement>(`#corruption-panel-${playerId} .corruption-marker`)) {
					if (marker.textContent) continue
					marker.classList.add('selectable')
					marker.onclick = () => {
						if (marker.textContent || !this.bga.players.isCurrentPlayerActive()) return
						const card = this.game.river.getSelection()[0]
						if (!card) {
							this.bga.dialogs.showMessage(_('Select a card from the river'), 'error')
							return
						}
						const boardSlot = Math.floor(Number(marker.id.split('-').pop()) / 10)
						const slot = Number(this.game.getCurrentPlayer().playerNo) === 1 ? boardSlot : 7 - boardSlot
						this.game.takeAction('actCorrupt', { cardId: card.id, slot })
					}
					this.selectableMarkers.push(marker)
				}
			}
			if (args.canProgressOnProp) {
				this.bga.statusBar.setTitle(_('${you} can move your propaganda marker'))
			}
		}
	}

	onLeavingState(args: PlayerDecisionArgs, isCurrentPlayerActive: boolean) {
		for (const marker of this.selectableMarkers) {
			marker.onclick = null
			marker.classList.remove('selectable')
		}
		this.selectableMarkers = []
		this.game.river.setSelectionMode('none')
	}
}
