import { CardsManager } from './cards/cards'
import { BgaAnimations } from './libs'

/**
 * Your game interfaces
 */
type PowerType = 'C' | 'B' | 'N'

// remove this if you don't use cards. If you do, make sure the types are correct . By default, some number are send as string, I suggest to cast to right type in PHP.
interface Card {
	id: number
	location: string
	location_arg: number
	type: number
	type_arg: number
}
interface SanCard extends Card {
	name: string //translated
}
interface NationTile extends Card {}

interface SanPlayer extends Player {
	playerNo: number
	cardsCount: number
	tickets: number
}

interface SanGamedatas {
	current_player_id: string
	teamMate?: number
	decision: { decision_type: string }
	game_result_neutralized: string
	gamestate: Gamestate
	gamestates: { [gamestateId: number]: Gamestate }
	neutralized_player_id: string
	notifications: { last_packet_id: string; move_nbr: string }
	playerorder: (string | number)[]
	playerOrderWorkingWithSpectators: number[] //starting with current player
	players: { [playerId: number]: SanPlayer }
	tablespeed: string
	lastTurn: boolean
	turnOrderClockwise: boolean
	expansion: number
	// counters
	version: string
	counters: Map<string, CounterValue>
	// Add here variables you set up in getAllDatas
	hand: Array<SanCard>
}

interface CounterValue {
	counter_name: string
	counter_value: number
}

interface SanGame /*extends Game*/ {
	bga: Bga
	cardsManager: CardsManager
	animationManager: InstanceType<typeof BgaAnimations.Manager>
	getCurrentPlayer(): SanPlayer
	getPlayerId(): number
	getPlayerScore(playerId: number): number
	setTooltip(id: string, html: string): void
	setTooltipToClass(className: string, html: string): void
	clientActionData: ClientActionData
	resetClientActionData(): void
	addTooltipOnClickHelpButton(idButton: string, tooltipContent: string, delay?: number): void
	handSelectionChange(selection: SanCard[], lastChange: SanCard): void
	takeAction(action: string, data?: any, options?: { lock: boolean; checkAction: boolean }): Promise<void>
}

interface PlayerTurnArgs {
	canUndo: boolean
	canCancel: boolean
	canPass: boolean
	selectableHandCards: NationTile[]
	canUseHammer: Boolean
	canUseFairy: Boolean
	ghostBlockedSquareIds: number[]
}

interface NotifPointsArgs {
	playerId: number
	points: number
	delta: number
	scoreType: string
}

interface NotifCounter {
	counterName: string
	counterValue: number
	playerId: number
}

interface NotifUpdateCounters {
	counters: [{ [name: string]: CounterValue }]
}

interface NotifScorePointArgs {
	playerId: number
	points: number
}

interface NotifImportantMessageArgs {
	message: string
	type: 'POSITIVE' | 'NEGATIVE' | 'WARNING' | 'WIN'
	temporary: boolean
	args: Array<any>
}

type MoveLocation = 'HAND' | 'DECK' | 'STOCK' | 'TABLE' | 'DISCARD' | 'square' | 'BOARD'

interface NotifMaterialMove {
	type: MaterialType
	from: MoveLocation
	to: MoveLocation
	fromArg: number
	toArg: number
	material: Array<any | string> //elements (cards for exemple), or tokenIds
}

interface SwappedMaterial {
	from: 'HAND' | 'DECK' | 'FESTIVAL'
	to: 'HAND' | 'DECK' | 'FESTIVAL'
	fromArg: number
	toArg: number
	material: any | string
}

type MaterialType = 'CARD' | 'TOKEN' | 'FIRST_PLAYER_TOKEN' | 'TILE' | 'TREASURE'

interface NotifMaterialSwap {
	type: MaterialType
	material1: SwappedMaterial
	material2: SwappedMaterial
}

interface ClientActionData {
	placedCardId: string | null
	destinationSquare: string | null
	previousCardParentInHand: HTMLElement | null
}
