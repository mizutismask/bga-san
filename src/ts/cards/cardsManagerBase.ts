import { BgaCards } from '../libs';
import { Tooltipable, TooltipElement } from '../tooltipable'

export abstract class CardsManagerBase<T> extends BgaCards.Manager<T> implements Tooltipable<T> {
	/**Generates the tooltip from getTooltipContent elements. */
	public getTooltip(card: T): string {
		const wrapper: HTMLElement = dojo.create('div', { class: 'tooltip-wrapper' })
		this.getTooltipContent().forEach((attr) => {
			const attrContent = attr.contentProvider(card)
			if (attrContent) {
				wrapper.appendChild(dojo.create('h3', { class: attr.classes, innerHTML: attr.title }))
				wrapper.appendChild(dojo.create('span', { innerHTML: attrContent }))
			}
		})
		return wrapper.outerHTML
	}

	public getTooltipContent(): TooltipElement<T>[] {
		return []
	}

	protected setBackground(cardDiv: HTMLDivElement, cardTypeArg: number, cardsUrl: string, imagesPerRow: number) {
		cardDiv.style.backgroundImage = `url('${cardsUrl}')`
		const imagePosition = cardTypeArg - 1
		const row = Math.floor(imagePosition / imagesPerRow)
		const xBackgroundPercent = (imagePosition - row * imagesPerRow) * 100
		const yBackgroundPercent = row * 100
		cardDiv.style.backgroundPositionX = `-${xBackgroundPercent}%`
		cardDiv.style.backgroundPositionY = `-${yBackgroundPercent}%`
		cardDiv.style.backgroundSize = `${imagesPerRow * 100}%`
	}
}
