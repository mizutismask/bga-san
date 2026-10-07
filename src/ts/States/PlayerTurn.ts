import { Game } from '../Game'
import { log } from '../base-game'
import { SanGamedatas, SanPlayer, PlayerTurnArgs } from '../types'
import { Utils } from '../utils'
import {
	CARD_TYPE_PROPAGANDA,
	CARD_TYPE_VIRUS,
	SPECIAL_EFFECT_NONE,
	SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD,
	SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD,
	SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD
} from '../constants'

/**
 * We create one State class per declared state on the PHP side, to handle all state specific code here.
 * onEnteringState, onLeavingState and onPlayerActivationChange are predefined names that will be called by the framework.
 * When executing code in this state, you can access the args using this.args
 */
export class PlayerTurn {
	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, Gamedatas<SanPlayer>>
	) {}

	/**
	 * This method is called each time we are entering the game state. You can use this method to perform some user interface changes at this moment.
	 */
	onEnteringState(args: PlayerTurnArgs, isCurrentPlayerActive: boolean) {
		for (const [typeArg, button] of Object.entries(
			this.game.playerTables[this.game.getPlayerId()]?.playAllButtons ?? {}
		)) {
			if (Number(typeArg) === CARD_TYPE_PROPAGANDA) {
				button.textContent = isCurrentPlayerActive
					? this.bga.gameui.format_string(_('Play all (next: ${cost})'), { cost: String(args.propagandaCost) })
					: _('Play all')
			}
			button.disabled =
				!isCurrentPlayerActive ||
				!args.selectableHandCards.some(
					(card) =>
						Number(card.type_arg) === Number(typeArg) &&
						!card.chooseOne &&
						!card.draw &&
						!card.destroyCards &&
						[
							SPECIAL_EFFECT_NONE,
							SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD,
							SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD,
							SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD
						].includes(card.specialEffect)
				)
		}
		/*this.bga.statusBar.setTitle(
			isCurrentPlayerActive ? _('${You} must play cards from your hand') : _('${actplayer} must play cards from his hand')
		)*/
		if (isCurrentPlayerActive) {
			log('selectableHandCards', args.selectableHandCards)
			//this.game.playerTables[this.game.getPlayerId()].setHandSelectionMode('single', args.selectableHandCards)

			/*const handStocks = Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)
			const updatePlaySelectedCardsButton = () => {
				document.getElementById('playSelectedCards')?.classList.toggle(
					'disabled',
					!handStocks.some(stock => stock.getSelection().length > 0)
				)
			}
			this.bga.statusBar.addActionButton(_('Play selected cards'), () => {
				const cardIds = handStocks.flatMap(stock => stock.getSelection().map(card => card.id))
				if (cardIds.length > 0) {
					this.game.takeAction('actPlaySelectedCards', { cardIds: cardIds.join(',') })
				}
			}, {
				id: 'playSelectedCards',
				color: 'primary'
			})
			for (const stock of handStocks) {
				stock.onSelectionChange = updatePlaySelectedCardsButton
			}
			updatePlaySelectedCardsButton()*/

			const selectableCardIds = new Set(args.selectableHandCards.map((card) => card.id))
			for (const stock of Object.values(this.game.playerTables[this.game.getPlayerId()].handStocks)) {
				const selectableCards = stock.getCards().filter((card) => selectableCardIds.has(card.id))
				stock.setSelectionMode(selectableCards.length > 0 ? 'single' : 'none', selectableCards)
				stock.onSelectionChange = (selection, lastChange) => {
					if (lastChange && selection.some((card) => card.id === lastChange.id)) {
						this.game.playCardWithConfirmation(lastChange, 'actPlayCard', { cardId: lastChange.id, choice: 0 })
					}
				}
			}

			if (args.canPass) {
				this.bga.statusBar.addActionButton(_('Validate my choices'), () => this.game.takeAction('actPass'), {
					id: 'buttonPass',
					color: 'primary',
					confirm: () => {
						const warnings: string[] = []
						if (
							this.game.propagandaCounters.get(this.game.getPlayerId())!.getValue() > 0 &&
							this.game.propagandaCounters.get(this.game.getPlayerId())!.getValue() < args.propagandaCost
						) {
							warnings.push(_('You do not have enough propaganda to advance on the track.'))
						}
						if (args.selectableHandCards.some((card) => Number(card.type_arg) === CARD_TYPE_VIRUS)) {
							warnings.push(_('You still have Virus cards in your hand.'))
						}
						return warnings.length > 0 ? [...warnings, _('Validate your choices anyway?')].join(' ') : undefined
					}
				})
			}

			this.bga.statusBar.addActionButton(
				_('Reset possible actions'),
				() => this.game.takeAction('actResetPlayerTurn'),
				{
					id: 'buttonReset',
					color: 'alert'
				}
			)

			//this.game.board.grid.onSlotClick = (slotId: number | string) => this.game.onSquareClick(slotId)
			/*this.game.playerTables[this.game.getPlayerId()].handStock!.onSelectionChange = (
				selection: NationTile[],
				lastChange: NationTile | null
			) => {
				this.game.handSelectionChange(selection, lastChange)
			}*/
		} else {
			//this.game.playerTables[this.game.getPlayerId()].setHandSelectionMode('none', undefined)
		}
		this.toggleActionButtons(args, isCurrentPlayerActive)
	}

	private toggleActionButtons(args: PlayerTurnArgs, isCurrentPlayerActive: boolean) {
		document.getElementById('buttonPass')?.classList.toggle('disabled', !args.canPass)
		document.getElementById('buttonCancel')?.classList.toggle('disabled', !args.canCancel)
		document.getElementById('buttonReset')?.classList.toggle('disabled', !args.canResetTurn)
	}

	/**
	 * This method is called each time we are leaving the game state. You can use this method to perform some user interface changes at this moment.
	 */
	onLeavingState(args: PlayerTurnArgs, isCurrentPlayerActive: boolean) {
		for (const button of Object.values(this.game.playerTables[this.game.getPlayerId()]?.playAllButtons ?? {})) {
			button.disabled = true
		}
		for (const stock of Object.values(this.game.playerTables[this.game.getPlayerId()]?.handStocks ?? {})) {
			stock.onSelectionChange = undefined
			stock.setSelectionMode('none')
		}
	}
}
