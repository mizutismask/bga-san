export class Utils {
	static addTemporaryClass(element: HTMLElement | string, className: string, removalDelay: number) {
		const el = typeof element === 'string' ? document.getElementById(element) : element
		if (!el) return

		el.classList.add(className)
		setTimeout(() => el.classList.remove(className), removalDelay)
	}

	static removeClass(className: string, rootNode?: HTMLElement | Document): void {
		if (!rootNode) rootNode = document
		else rootNode = rootNode as HTMLElement
		rootNode.querySelectorAll('.' + className).forEach((item) => item.classList.remove(className))
	}

	static getPart(haystack: string, i: number, noException: boolean = false, separator = '-'): string {
		const parts: string[] = haystack.split(separator)
		const len: number = parts.length

		if (noException && i >= len) {
			return ''
		}
		if (noException && len + i < 0) {
			return ''
		}
		return parts[i >= 0 ? i : len + i]
	}

	static replaceStarScoreIcon(newClass: string) {
		dojo.query('.fa-star')
			.removeClass('fa fa-star')
			.addClass(newClass)
			.style({ 'vertical-align': 'middle', 'display': 'inline-block' })
	}

	static createDiv(classes: string, id: string = '', value: string = '') {
		if (typeof value == 'undefined') value = ''
		const node: HTMLElement = dojo.create('div', { class: classes, innerHTML: value })
		if (id) node.id = id
		return node.outerHTML
	}

	static groupBy<T>(arr: T[], fn: (item: T) => any) {
		return arr.reduce<Record<string, T[]>>((prev, curr) => {
			const groupKey = fn(curr)
			const group = prev[groupKey] || []
			group.push(curr)
			return { ...prev, [groupKey]: group }
		}, {})
	}
}
