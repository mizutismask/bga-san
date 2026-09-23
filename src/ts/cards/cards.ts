import { TooltipElement } from '../tooltipable'
import { SanCard, SanGame } from '../types'
import { CardsManagerBase } from './cardsManagerBase'

// <reference path="../card-manager.ts"/>
export const IMAGE_ITEMS_PER_ROW = 13
const modifierCardSize = .7
const CARD_WIDTH = 200 * modifierCardSize
const CARD_HEIGHT = 279 * modifierCardSize

const setupFrontDiv = (game: SanGame) => (card: SanCard, div: HTMLElement) => {
	game.cardsManager.setFrontBackground(div as HTMLDivElement, card.type)
	const choicesId = `${game.cardsManager.getId(card)}-choices`
	const textId = `${game.cardsManager.getId(card)}-text`

	//add help
	const helpId = `${game.cardsManager.getId(card)}-front-info`
	if (!$(helpId)) {
		const info: HTMLDivElement = document.createElement('div')
		info.id = helpId
		info.innerText = '?'
		info.classList.add('css-icon', 'card-info')
		div.appendChild(info)
		if (game.cardsManager.isCardVisible(card)) {
			const tooltipContent = game.cardsManager.getTooltip(card)
			game.setTooltip(div.id, tooltipContent)
			game.addTooltipOnClickHelpButton(info.id, tooltipContent)
		}
	}

	if (card.chooseOne && !$(choicesId)) {
		const container: HTMLDivElement = document.createElement('div')
		container.id = choicesId
		container.classList.add('card-choices')
		const choiceCount = Number(card.type_arg) === 4 ? 3 : 2
		container.classList.toggle('wide-choices', choiceCount === 2)
		for (let choice = 1; choice <= choiceCount; choice++) {
			const zone = document.createElement('button')
			zone.type = 'button'
			zone.classList.add('card-choice')
			zone.setAttribute('aria-label', `${_('Choice')} ${choice}`)
			zone.addEventListener('click', event => {
				event.stopPropagation()
				const hand = document.getElementById(`hand-${game.getPlayerId()}`)
				if (!hand?.contains(zone)) {
					return
				}
				game.takeAction('actPlayCard', { cardId: card.id, choice })
			})
			container.appendChild(zone)
		}
		div.appendChild(container)
	}

	if (!$(textId)) {
		const container: HTMLDivElement = document.createElement('div')
		container.id = textId
		container.classList.add('bga-autofit', 'card-text-wrapper')
		div.appendChild(container)
	}
}

export class CardsManager extends CardsManagerBase<SanCard> {
	constructor(public game: SanGame) {
		super({
			animationManager: game.animationManager,
			type: 'card',
			getId: (card) => `san-card-${card.id}`,
			setupFrontDiv: setupFrontDiv(game),
			setupDiv: (card: SanCard, div: HTMLElement) => {
				div.classList.add('san-card')
				div.classList.toggle('has-choices', card.chooseOne)
				div.dataset.cardId = '' + card.id
				div.dataset.cardType = '' + card.type
			},
			setupBackDiv: (card: SanCard, div: HTMLElement) => {
				div.style.backgroundImage = `url('${g_gamethemeurl}img/san-card-background.jpg')`
				//	const url = this.game.bga.images.getImgUrl("treasures.webp")
				//	div.style.backgroundImage = `url('${url}')`
				//	div.style.backgroundSize = `${IMAGE_ITEMS_PER_ROW * 100}%`
			},
			cardHeight: CARD_HEIGHT,
			cardWidth: CARD_WIDTH,
			cardBorderRadius: '3px'
		})
	}

	public getCardName(card: SanCard) {
		return `<div class="cstm-card-name">${card.name}</div>`
	}

	public getTooltipContent(): TooltipElement<SanCard>[] {
		return [{ title: _('Objective'), contentProvider: (c: SanCard) => this.getDesc(c) }]
	}

	public getDesc(card: SanCard) {
		return 'todo'
	}

	public setFrontBackground(cardDiv: HTMLDivElement, cardType: number) {
		const imageUrl = this.game.bga.images.getImgUrl('cards.webp')
		cardDiv.style.backgroundImage = `url('${imageUrl}')`
		const imagePosition = cardType
		const row = Math.floor(imagePosition / IMAGE_ITEMS_PER_ROW)
		const xBackgroundPercent = (imagePosition - row * IMAGE_ITEMS_PER_ROW) * 100
		const yBackgroundPercent = row * 100
		cardDiv.style.backgroundPositionX = `-${xBackgroundPercent}%`
		cardDiv.style.backgroundPositionY = `-${yBackgroundPercent}%`
		cardDiv.style.backgroundSize = `${IMAGE_ITEMS_PER_ROW * 100}%`
	}
}
