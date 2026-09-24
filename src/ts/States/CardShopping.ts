import { Game } from '../Game'
import { CardShoppingArgs, SanGamedatas, SanPlayer } from '../types'

export class CardShopping {
	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, SanGamedatas>
	) {}

	onEnteringState(args: CardShoppingArgs, isCurrentPlayerActive: boolean) {
		if (!isCurrentPlayerActive) return
		this.game.river.setSelectionMode('single', args.possibleCards)
		this.bga.statusBar.addActionButton(_('Buy selected card'), () => {
			const card = this.game.river.getSelection()[0]
			if (card) {
				this.game.takeAction('actBuyCard', { cardId: card.id })
			}
		}, {
			id: 'buttonBuyCard',
			color: 'primary'
		})
		const updateBuyButton = () => {
			document.getElementById('buttonBuyCard')?.classList.toggle('disabled', this.game.river.getSelection().length === 0)
		}
		this.game.river.onSelectionChange = updateBuyButton
		updateBuyButton()

		if (args.canPass) {
			this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'secondary'
			})
		}
	}

	onLeavingState(args: CardShoppingArgs, isCurrentPlayerActive: boolean) {
		this.game.river.onSelectionChange = undefined
		this.game.river.setSelectionMode('none')
	}
}
