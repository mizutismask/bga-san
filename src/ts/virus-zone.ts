import { Deck } from '../../bga-cards'
import { BgaCards } from './libs'
import { SanCard, SanGame, SanGamedatas } from './types'

export class VirusZone {
	public readonly decks: Record<number, Deck<SanCard>> = {}

	constructor(game: SanGame, gamedatas: SanGamedatas, container: HTMLElement) {
		const zone = document.createElement('div')
		zone.id = 'virus-zone'
		zone.className = 'virus-zone'
		container.prepend(zone)

		const players = [...gamedatas.playerOrderWorkingWithSpectators].reverse()
		players.forEach((playerId, index) => {
			if (index === 1) {
				const marker = document.createElement('div')
				marker.className = 'virus-central-marker'
				marker.textContent = _('Virus')
				zone.appendChild(marker)
			}
			const section = document.createElement('div')
			section.className = 'virus-player-deck'
			section.classList.toggle('own', Number(playerId) === game.getPlayerId())
			const deckElement = document.createElement('div')
			deckElement.id = `virus-deck-${playerId}`
			section.appendChild(deckElement)
			zone.appendChild(section)
			const deck = new BgaCards.Deck<SanCard>(game.cardsManager, deckElement, {
				cardNumber: 0,
				autoRemovePreviousCards: false,
				counter: { show: false }
			})
			this.decks[playerId] = deck
			deck.addCards([...(gamedatas.virusCards[playerId] ?? [])].sort((a, b) => a.location_arg - b.location_arg))
		})
	}
}
