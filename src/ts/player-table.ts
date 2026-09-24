import { LineStock } from '../../bga-cards'
import { BgaCards } from './libs'
import { SanCard, SanGame, SanPlayer } from './types'

/**
 * Player table.
 */
export class PlayerTable {
	public readonly handStocks: Record<number, LineStock<SanCard>> = {}

	constructor(
		private game: SanGame,
		player: SanPlayer,
		cards: SanCard[] = []
	) {
		const isMyTable = Number(player.id) === game.getPlayerId()
		const ownClass = isMyTable ? 'own' : ''
		let html = `
			<a id="anchor-player-${player.id}"></a>
            <div id="player-table-${player.id}" class="player-order${player.playerNo} player-table ${player.symbol} ${ownClass}">
				<span class="player-name" style="color:#${player.color}">${player.name}</span>
            </div>
        `
		dojo.place(html, 'player-tables')
		if (isMyTable) {
			this.initHand(player, cards)
		}
	}

	private initHand(player: SanPlayer, cards: SanCard[]) {
		const container = document.createElement('div')
		container.id = `hand-${player.id}`
		container.classList.add('cstm-player-hand')
		document.getElementById(`player-table-${player.id}`)!.prepend(container)

		for (const typeArg of [1, 2, 3, 4]) {
			const element = document.createElement('div')
			element.id = `${container.id}-type-${typeArg}`
			container.appendChild(element)
			const stock = new BgaCards.LineStock<SanCard>(this.game.cardsManager, element, {
				direction: 'column',
				wrap: 'nowrap',
				center: false
			})
			this.handStocks[typeArg] = stock
			stock.setSelectionMode('multiple')
			stock.addCards(cards.filter((card) => Number(card.type_arg) === typeArg))
			stock.onSelectionChange = (selection, lastChange) => {
				if (lastChange && selection.some((card) => card.id === lastChange.id)) {
					this.game.takeAction('actPlayCard', { cardId: lastChange.id, choice: 0 })
				}
			}
		}
	}
}
