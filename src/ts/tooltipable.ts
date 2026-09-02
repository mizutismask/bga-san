export interface Tooltipable<T> {
	/**
	 * Return the list of elements that constitute the tooltip.
	 */
	getTooltipContent(): TooltipElement<T>[]
}

/**
 * One element of information in the tooltip
 */
export interface TooltipElement<T> {
	/** Translated title of the element. Gives a h3 tag if some content is present. */
	title: string
	/** Method to get the content of the description. */
	contentProvider: (c: T) => string
	/** Classes to add to the title. */
	classes?: string
}
