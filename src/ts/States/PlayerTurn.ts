import { Game} from '../Game'
import { log } from '../base-game'
import { SanGamedatas, SanPlayer, PlayerTurnArgs } from '../types'
import { Utils } from '../utils'

/**
 * We create one State class per declared state on the PHP side, to handle all state specific code here.
 * onEnteringState, onLeavingState and onPlayerActivationChange are predefined names that will be called by the framework.
 * When executing code in this state, you can access the args using this.args
 */
export class PlayerTurn {
	constructor(
		private game: Game,
		private bga: Bga<SanPlayer, SanGamedatas>
	) {}

	/**
	 * This method is called each time we are entering the game state. You can use this method to perform some user interface changes at this moment.
	 */
	onEnteringState(args: PlayerTurnArgs, isCurrentPlayerActive: boolean) {
		this.bga.statusBar.setTitle(
			isCurrentPlayerActive ? _('You must end your turn') : _('${actplayer} must place a tile or end his turn')
		)

		if (isCurrentPlayerActive) {
			
			log('selectableHandCards', args.selectableHandCards)
			//this.game.playerTables[this.game.getPlayerId()].setHandSelectionMode('single', args.selectableHandCards)
			if (args.canUseFairy) {
				this.bga.statusBar.addActionButton(
					_('Use fairy'),
					() => {
						this.game.takeAction('actUseToken', { tokenType: 1 })
					},
					{ tooltip: _('You’ll be able to place dwarves and mermaids on any square') }
				)
			}
			this.bga.statusBar.addActionButton(_('End turn'), () => this.game.takeAction('actPass'), {
				id: 'buttonPass',
				color: 'primary'
			})
			
			this.bga.statusBar.addActionButton(_('Undo'), () => this.game.takeAction('actUndo', { qty: -1 }), {
				id: 'buttonUndo',
				color: 'alert'
			})

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
		document.getElementById('buttonUndo')?.classList.toggle('disabled', !args.canUndo)
	}

	/**
	 * This method is called each time we are leaving the game state. You can use this method to perform some user interface changes at this moment.
	 */
	onLeavingState(args: PlayerTurnArgs, isCurrentPlayerActive: boolean) {
		//this.game.playerTables[this.game.getPlayerId()].setHandSelectionMode('none', undefined)
	}
}
