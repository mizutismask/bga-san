import { TooltipElement } from '../tooltipable'
import { SanCard, SanGame } from '../types'
import { CardsManagerBase } from './cardsManagerBase'

// <reference path="../card-manager.ts"/>
export const IMAGE_ITEMS_PER_ROW = 13
const modifierCardSize = .7
const CARD_WIDTH = 200 * modifierCardSize
const CARD_HEIGHT = 279 * modifierCardSize

// Square centers in cards.webp, in percentages, following the printed path.
const VIRUS_SLOT_POSITIONS: Record<number, [number, number][]> = {
	3: [[34, 61.5], [38, 18], [75, 46]],
	4: [[28, 83], [27, 40], [71, 23], [68.5, 68]],
	5: [[33, 61.5], [27, 40], [71, 23], [75, 46], [68.5, 68]],
	6: [[33, 61.5], [27, 40], [38, 18], [71, 23], [75, 46], [68.5, 68]],
	7: [[28, 83], [33, 61.5], [27, 40], [38, 18], [71, 23], [75, 46], [68.5, 68]]
}

const setupFrontDiv = (game: SanGame) => (card: SanCard, div: HTMLElement) => {
	game.cardsManager.setFrontBackground(div as HTMLDivElement, card.type)
	const choicesId = `${game.cardsManager.getId(card)}-choices`
	const textId = `${game.cardsManager.getId(card)}-text`

	div.querySelectorAll('.virus-slot').forEach(slot => slot.remove())
	const virusPositions = VIRUS_SLOT_POSITIONS[card.virusSpaces] ?? []
	virusPositions.forEach(([left, top], index) => {
		const slot = document.createElement('div')
		slot.id = `${game.cardsManager.getId(card)}-virus-slot-${index + 1}`
		slot.className = 'virus-slot'
		slot.dataset.slot = `${index + 1}`
		slot.style.left = `${left}%`
		slot.style.top = `${top}%`
		div.appendChild(slot)
	})

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
				if (document.getElementById('immediate-discard-cards')?.contains(zone)) {
					game.playCardWithConfirmation(card, 'actPlayFromDiscard', { cardId: card.id, choice })
					return
				}
				const hand = document.getElementById(`hand-${game.getPlayerId()}`)
				if (!hand?.contains(zone)) {
					return
				}
				game.playCardWithConfirmation(card, 'actPlayCard', { cardId: card.id, choice })
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
		return [
			{ title: '', contentProvider: (card: SanCard) => this.getDesc(card) },
			{
				title: '',
				contentProvider: (card: SanCard) => card.income > 0
					? this.game.bga.gameui.format_string_recursive(_('+${amount} Income'), { amount: card.income })
					: ''
			}
		]
	}

	public getDesc(card: SanCard) {
		if (card.virusSpaces > 0) {
			return _("When the Virus token leaves the last space of this card, this card goes on top of its owner's deck")
		}

		const effects: string[] = []
		const amount = (text: string, value: number) => this.game.bga.gameui.format_string_recursive(text, { amount: value })
		switch (Number(card.specialEffect)) {
			case 3:
				effects.push(_('Gain 1 Propaganda for each Propaganda card you played this turn (including this one)'))
				break
			case 5:
				effects.push(_('Move 1 space on the Virus track for each Virus card you played this turn (including this one)'))
				break
			case 7:
				effects.push(_('Gain 1 Corruption for each Corruption card you played this turn (including this one)'))
				break
			default:
				if (card.propaganda > 0) effects.push(amount(_('Gain ${amount} Propaganda'), card.propaganda))
				if (card.hacking > 0) effects.push(amount(_('Move ${amount} space(s) on the Virus track'), card.hacking))
				if (card.corruption > 0) effects.push(amount(_('Gain ${amount} Corruption'), card.corruption))
		}
		if (card.draw > 0) effects.push(amount(_('Draw ${amount} card(s) from your deck'), card.draw))

		const parts: string[] = []
		if (effects.length) {
			parts.push(card.chooseOne
				? `<div>${_('Choose one:')}</div><ul>${effects.map(effect => `<li>${effect}</li>`).join('')}</ul>`
				: effects.map(effect => `<div>${effect}</div>`).join(''))
		}
		switch (Number(card.specialEffect)) {
			case 8:
				parts.push(_('Play a card from your discard'))
				break
			case 9:
				parts.push(_('Copy the type and effect of a card you played this turn (including the trash after use effect if any)'))
				break
			case 10:
				parts.push(_('Put 1 card from your hand into a corruption slot'))
				break
			case 11:
				parts.push(_('You can play all types of cards this turn'))
				break
			case 12:
				parts.push(_('Copy the type and effect of a card in the river (including the trash after use effect if any)'))
				break
		}
		if (card.destroyCards > 0) parts.push(amount(_('Destroy up to ${amount} card(s) from your hand'), card.destroyCards))
		if (card.trashAfterUse) parts.push(_('Trash after use'))
		return parts.map(part => `<div>${part}</div>`).join('')
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
