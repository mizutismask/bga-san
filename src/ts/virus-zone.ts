import { Deck } from '../../bga-cards'
import { BgaCards } from './libs'
import { SanCard, SanGame, SanGamedatas } from './types'

export class VirusZone {
	public readonly decks: Record<number, Deck<SanCard>> = {}
	private readonly token = document.createElement('div')
	private readonly centralMarker = document.createElement('div')
	private position = 0
	private ready = false

	constructor(private game: SanGame, private gamedatas: SanGamedatas, container: HTMLElement) {
		const zone = document.createElement('div')
		zone.id = 'virus-zone'
		zone.className = 'virus-zone'
		container.prepend(zone)
		this.token.id = 'virus-token'
		this.token.className = 'virus-token'
		this.token.title = _('Virus')
		this.token.innerHTML = '<i class="fa fa-bug" aria-hidden="true"></i>'

		const players = [...gamedatas.playerOrderWorkingWithSpectators].reverse()
		const decksReady = players.map((playerId, index) => {
			if (index === 1) {
				const marker = this.centralMarker
				marker.className = 'virus-central-marker'
				marker.textContent = _('Virus')
				zone.appendChild(marker)
			}
			const section = document.createElement('div')
			section.className = `virus-player-deck ${gamedatas.players[playerId].symbol}`
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
			return deck.addCards([...(gamedatas.virusCards[playerId] ?? [])].sort((a, b) => a.location_arg - b.location_arg))
		})

		const counterElement = document.createElement('span')
		counterElement.hidden = true
		zone.appendChild(counterElement)
		const counter = new ebg.counter()
		counter.create(counterElement, {
			tableCounter: 'virusTokenPosition',
			value: Number(gamedatas.virusTokenPosition)
		})
		this.position = Number(counter.getValue())
		for (const method of ['setValue', 'toValue'] as const) {
			const updateCounter = counter[method].bind(counter)
			counter[method] = (value: number) => {
				updateCounter(value)
				this.position = Number(value)
				this.refreshToken()
			}
		}
		Promise.all(decksReady).then(() => {
			this.ready = true
			this.refreshToken()
		})
	}

	public refreshToken() {
		if (!this.ready) return

		if (this.position === 0) {
			this.centralMarker.appendChild(this.token)
			return
		}
		const playerNo = this.position < 0 ? 1 : 2
		const playerId = this.gamedatas.playerOrderWorkingWithSpectators.find(
			id => Number(this.gamedatas.players[id].playerNo) === playerNo
		)
		const card = playerId === undefined ? null : this.decks[playerId]?.getTopCard()
		const slot = card && document.getElementById(
			`${this.game.cardsManager.getId(card)}-virus-slot-${Math.abs(this.position)}`
		)
		if (slot) {
			slot.appendChild(this.token)
		} else {
			this.token.remove()
		}
	}
}
