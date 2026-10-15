import { Deck, LineStock } from '../../bga-cards'
import { BgaCards } from './libs'
import { SanCard, SanGame, SanPlayer } from './types'
import { CARD_TYPE_CORRUPTION, CARD_TYPE_PROPAGANDA, CARD_TYPE_HACKING, CARD_TYPE_HARDWARE, CARD_TYPE_VIRUS } from './constants'

/**
 * Player table.
 */
export class PlayerTable {
	public readonly handStocks: Record<number, LineStock<SanCard>> = {}
	public readonly playAllButtons: Record<number, HTMLButtonElement> = {}
	public playerDeckElement?: HTMLDivElement
	public playerDeck?: Deck<SanCard>

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

		container.insertAdjacentHTML('beforeend', `
			<div class="hand-pile player-draw-pile">
				<span class="bgabutton bgabutton_blue play-all" style="visibility: hidden" aria-hidden="true"></span>
				<div id="player-draw-deck-${player.id}" class="player-draw-deck"></div>
			</div>
		`)
		this.playerDeckElement = document.getElementById(`player-draw-deck-${player.id}`) as HTMLDivElement
		const counterId = `player-draw-deck-counter-${player.id}`
		this.playerDeck = new BgaCards.Deck<SanCard>(this.game.cardsManager, this.playerDeckElement, {
			cardNumber: Number(player.remainingCardsInDeck),
			autoUpdateCardNumber: false,
			counter: { show: true, position: 'bottom-right', size: 6, counterId }
		})
		const counter = new ebg.counter()
		// Update the fake top card and thickness alongside the server-linked counter.
		for (const method of ['setValue', 'toValue'] as const) {
			const updateCounter = counter[method].bind(counter)
			counter[method] = (value: number) => {
				if (this.playerDeck!.getCardNumber() !== value) {
					void this.playerDeck!.setCardNumber(value)
				}
				updateCounter(value)
			}
		}
		counter.create(counterId, {
			value: Number(player.remainingCardsInDeck),
			playerCounter: 'remainingCardsInDeck',
			playerId: Number(player.id)
		})
		this.game.setTooltip(this.playerDeckElement.id, _('Remaining cards in deck'))

		for (const typeArg of [CARD_TYPE_VIRUS,CARD_TYPE_CORRUPTION, CARD_TYPE_PROPAGANDA, CARD_TYPE_HACKING, CARD_TYPE_HARDWARE]) {
			const pile = document.createElement('div')
			pile.className = 'hand-pile'
			container.appendChild(pile)
			if (typeArg !== CARD_TYPE_HARDWARE) {
				const button = document.createElement('button')
				button.type = 'button'
				button.className = 'bgabutton bgabutton_blue play-all'
				button.title = _('Play all simple cards, others will be ignored')
				button.disabled = true
				button.addEventListener('click', () => this.game.takeAction('actPlayAll', { typeArg }))
				pile.appendChild(button)
				this.playAllButtons[typeArg] = button
			} else {
				const spacer = document.createElement('span')
				spacer.className = 'bgabutton bgabutton_blue play-all'
				spacer.style.visibility = 'hidden'
				spacer.setAttribute('aria-hidden', 'true')
				pile.appendChild(spacer)
			}
			const element = document.createElement('div')
			element.id = `${container.id}-type-${typeArg}`
			pile.appendChild(element)
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
