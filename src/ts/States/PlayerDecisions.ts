import { Game } from '../Game'
import { SanGamedatas, SanPlayer, PlayerDecisionArgs } from '../types'

export class PlayerDecisions {
	private selectableMarkers: HTMLElement[] = []

	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, Gamedatas<SanPlayer>>
	) {}

	onEnteringState(args: PlayerDecisionArgs, isCurrentPlayerActive: boolean) {
		this.game.updateProgressionCost(isCurrentPlayerActive ? args.propagandaCost : null)
		if (isCurrentPlayerActive) {
			if (args.canCorrupt) {
				this.bga.statusBar.setTitle(_('${you} can corrupt a card from the river and choose where to place it'))
				this.game.river.setSelectionMode('single')
				this.selectableMarkers = this.game.highlightCorruptionSlots((slot) => {
					const card = this.game.river.getSelection()[0]
					if (!card) {
						this.bga.dialogs.showMessage(_('Select a card from the river'), 'error')
						return
					}
					this.game.takeAction('actCorrupt', { cardId: card.id, slot })
				})
			}
			if (args.canProgressOnProp) {
				this.bga.statusBar.addActionButton(_('Progress on propaganda'), () => this.game.takeAction('actProgressOnProp'), {
					id: 'progressOnPropaganda',
					color: 'primary'
				})
			}
			if (args.canHack) {
				this.bga.statusBar.addActionButton(_('Progress on hacking'), () => this.game.takeAction('actProgressOnHacking'), {
					id: 'progressOnHacking',
					color: 'primary'
				})
			}
			this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'alert'
			})
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
