import { Game } from '../Game'
import { ImmediateActionArgs, SanCard, SanGamedatas, SanPlayer } from '../types'
import { LineStock } from '../../../bga-cards'
import { BgaCards } from '../libs'

export class ImmediateAction {
	private selectableMarkers: HTMLElement[] = []
	private discardStock?: LineStock<SanCard>

	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, SanGamedatas>
	) {}

	onEnteringState(
		args: ImmediateActionArgs,
		isCurrentPlayerActive: boolean
	) {
		if (!isCurrentPlayerActive) return
        const copyCards = args._private?.copyCards ?? []
        if (copyCards.length) {
            const copyFromRiver = args._private?.copyFromRiver ?? false
            const copyStock = copyFromRiver ? this.game.river : this.game.playedCards
            const copyAction = copyFromRiver ? 'actCopyRiverCard' : 'actCopyPlayedCard'
            this.bga.statusBar.setTitle(copyFromRiver
                ? _('${you} can copy a card from the river')
                : _('${you} can copy a card you played this turn'))
            copyStock.setSelectionMode('single')
            copyStock.setSelectableCards(copyCards)
            copyStock.onSelectionChange = (selection, lastChange) => {
                for (let choice = 1; choice <= 3; choice++) {
                    document.getElementById(`buttonCopyChoice${choice}`)?.remove()
                }
                if (!lastChange || !selection.some(card => card.id === lastChange.id)) return
                const choiceCount = args._private?.copyChoices[lastChange.id] ?? 0
                if (!choiceCount) {
                    this.game.playCardWithConfirmation(lastChange, copyAction, { cardId: lastChange.id, choice: 0 })
                    return
                }
                for (let choice = 1; choice <= choiceCount; choice++) {
                    this.bga.statusBar.addActionButton(_('Option') + ' ' + choice, () => {
                        this.game.playCardWithConfirmation(lastChange, copyAction, { cardId: lastChange.id, choice })
                    }, { id: `buttonCopyChoice${choice}`, color: 'primary' })
                }
            }
            this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
                id: 'buttonPass', color: 'secondary'
            })
        }
		const discardCards = args._private?.discardCards ?? []
		if (discardCards.length) {
			this.bga.statusBar.setTitle(_('${you} can play a card from your discard pile'))
			if (!this.discardStock) {
				const container = document.createElement('div')
				container.id = 'immediate-discard-cards'
				document.getElementById('played-cards')!.before(container)
				this.discardStock = new BgaCards.LineStock<SanCard>(this.game.cardsManager, container)
			}
			document.getElementById('immediate-discard-cards')!.style.display = ''
			this.discardStock.addCards(discardCards, { initialSide: 'front', finalSide: 'front' })
			this.discardStock.setSelectionMode('single')
			this.discardStock.onSelectionChange = (selection, lastChange) => {
				if (lastChange && !lastChange.chooseOne && selection.some(card => card.id === lastChange.id)) {
					this.game.playCardWithConfirmation(lastChange, 'actPlayFromDiscard', { cardId: lastChange.id, choice: 0 })
				}
			}
			this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'secondary'
			})
		}
		if (args.corruptionSlots.length) {
			this.bga.statusBar.setTitle(_('${you} must select a card from your hand and choose where to corrupt it'))
			const stocks = Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)
			for (const stock of stocks) {
				stock.setSelectionMode('single')
				stock.onSelectionChange = (selection) => {
					if (selection.length) {
						for (const other of stocks) {
							if (other !== stock) other.unselectAll()
						}
					}
				}
			}
			for (const marker of document.querySelectorAll<HTMLElement>(
				`#corruption-panel-${this.game.getPlayerId()} .corruption-marker`
			)) {
				const boardSlot = Math.floor(Number(marker.id.split('-').pop()) / 10)
				const slot = Number(this.game.getCurrentPlayer().playerNo) === 1 ? boardSlot : 7 - boardSlot
				if (marker.textContent || !args.corruptionSlots.includes(slot)) continue
				marker.classList.add('selectable')
				marker.onclick = () => {
					if (marker.textContent || !this.bga.players.isCurrentPlayerActive()) return
					const card = stocks.flatMap((stock) => stock.getSelection())[0]
					if (!card) {
						this.bga.dialogs.showMessage(_('Select a card from your hand'), 'error')
						return
					}
					this.game.takeAction('actCorrupt', { cardId: card.id, slot })
				}
				this.selectableMarkers.push(marker)
			}
		}
		if (args.remainingDestroysFromHand) {
			this.bga.statusBar.setTitle(_('${you} can select up to ${remainingDestroysFromHand} card(s) from your hand to destroy'), args)
			const stocks = Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)
			const getSelectedCards = () => stocks.flatMap((stock) => stock.getSelection())
			const isValidSelection = () => {
				const count = getSelectedCards().length
				return count > 0 && count <= args.remainingDestroysFromHand
			}
			this.bga.statusBar.addActionButton(_('Destroy selected cards'), () => {
				if (isValidSelection()) {
					this.game.takeAction('actDestroy', { cardIds: getSelectedCards().map((card) => card.id).join(',') })
				}
			}, {
				id: 'buttonDestroyCards',
				color: 'primary'
			})
			const updateDestroyButton = () => {
				document.getElementById('buttonDestroyCards')?.classList.toggle('disabled', !isValidSelection())
			}
			for (const stock of stocks) {
				stock.onSelectionChange = updateDestroyButton
				stock.setSelectionMode('multiple')
			}
			updateDestroyButton()
			this.bga.statusBar.addActionButton(_('Pass'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'alert'
			})
		}
	}
	onLeavingState() {
        this.game.playedCards.onSelectionChange = undefined
        this.game.playedCards.setSelectionMode('none')
        this.game.river.onSelectionChange = undefined
        this.game.river.setSelectionMode('none')
		if (this.discardStock) {
			this.discardStock.onSelectionChange = undefined
			this.discardStock.setSelectionMode('none')
			document.getElementById('immediate-discard-cards')!.style.display = 'none'
			this.discardStock.removeAll()
		}
		for (const marker of this.selectableMarkers) {
			marker.onclick = null
			marker.classList.remove('selectable')
		}
		this.selectableMarkers = []
		for (const stock of Object.values(this.game.playerTables[this.game.getPlayerId()]?.handStocks ?? {})) {
			stock.onSelectionChange = undefined
			stock.setSelectionMode('none')
		}
	}
}
