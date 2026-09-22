import { BgaCards, BgaAnimations, BgaAutofit } from './libs'
import { BaseGame, log, isDebug, ANIMATION_MS, ACTION_TIMER_DURATION } from './base-game'
import { GameFeatureConfig } from './gamefeatureconfig'
import { PlayerTable } from './player-table'
import { VirusZone } from './virus-zone'
import { CardStock, Deck, SlotStock } from '../../bga-cards'
import { SanCard, SanGamedatas, SanPlayer, NotifMaterialMove } from './types'
import { CardsManager } from './cards/cards'
import { generateSlotsIds } from './stock-utils'

export class Game extends BaseGame {
	public cardsManager!: CardsManager
	public riverDeck!: Deck<SanCard>
	public river!: SlotStock<SanCard>
	public virusZone!: VirusZone
	private corruptedCardPositions = new Map<number, HTMLElement>()

	private propagandaCounters: Counter[] = []
	private ticketsCounters: Counter[] = []
	private handCardsCounters: Counter[] = []

	private displayedTooltip: any //dijit.Tooltip

	constructor(bga: Bga<SanPlayer, SanGamedatas>) {
		super()
		this.bga = bga
		//this.bga.states.register('PlayerTurn', new PlayerTurn(this, this.bga))
		this.bga.userPreferences.onChange = (pref_id, pref_value) => this.customPreferenceChanged(pref_id, pref_value)
	}

	public setup(gamedatas: SanGamedatas) {
		this.gamedatas = gamedatas
		log('Starting game setup')
		this.dontPreloadUselessAssets()

		this.includeHtmlBasicTemplate()
		this.gameFeatures = new GameFeatureConfig()
		log('gamedatas', gamedatas)

		this.animationManager = new BgaAnimations.Manager({
			animationsActive: () => this.gameui.bgaAnimationsActive()
		})
		this.cardsManager = new CardsManager(this)

		if (gamedatas.lastTurn) {
			this.notif_lastTurn()
		}
		if (Number(gamedatas.gamestate.id) >= 90) {
			// score or end
			this.onEnteringEndScore()
		}

		Object.values(this.gamedatas.playerOrderWorkingWithSpectators).forEach((p) => {
			this.setupPlayer(this.gamedatas.players[p])
		})

		$('overall-content').classList.add(`player-count-${this.getPlayersCount()}`)
		const hand = document.getElementById(`hand-${this.getPlayerId()}`)
		this.virusZone = new VirusZone(this, gamedatas, hand?.parentElement ?? document.getElementById('player-tables')!)

		this.setupPlaymat(this.gamedatas)
		this.setupTooltips()
		//this.setupHelpPopin()

		if (!this.isCustomSoundsOn()) {
			this.bga.sounds.dontPreloadSounds(this.customSounds)
		}
		this.setupNotifications()
		BgaAutofit.init()

		log('Ending game setup')
	}

	private setupPlaymat(gamedatas: SanGamedatas) {
		const centralLine = document.getElementById('central-line')!
		//deck
		centralLine.insertAdjacentHTML('beforeend', '<div id="river-deck"></div>')
		this.riverDeck = new BgaCards.Deck<SanCard>(this.cardsManager, document.getElementById('river-deck')!, {
			topCard: gamedatas.riverDeckTopCard ?? undefined,
			cardNumber: gamedatas.riverDeckCount,
			counter: { show: true, position:'left' }
		})

		// river
		centralLine.insertAdjacentHTML('beforeend', '<div id="river"></div>')
		const riverPrefix = 'river-slot-'
		this.river = new BgaCards.SlotStock<SanCard>(this.cardsManager, document.getElementById('river')!, {
			slotsIds: generateSlotsIds(riverPrefix, 6),
			mapCardToSlot: (card) => riverPrefix + card.location_arg
		})
		this.river.addCards(gamedatas.river)
		const playerOrder = gamedatas.playerOrderWorkingWithSpectators
		playerOrder.forEach((playerId, index) => {
			const player = gamedatas.players[playerId]
			const panel = document.createElement('div')
			panel.id = `corruption-panel-${playerId}`
			panel.className = `corruptionPanel ${index === 0 ? 'below-river' : 'above-river'}`
			panel.style.gridRow = index === 0 ? '3' : '1'
			const title = document.createElement('div')
			title.className = 'corruption-panel-title'
			title.style.color = `#${player.color}`
			title.textContent = `${player.name} — ${_('Corrupted cards')}`
			panel.appendChild(title)
			const slots = document.createElement('div')
			slots.className = 'corruption-slots'
			panel.appendChild(slots)
			centralLine.insertAdjacentElement(index === 0 ? 'beforeend' : 'afterbegin', panel)
			for (let slot = 1; slot <= 6; slot++) {
				const pair = document.createElement('div')
				pair.className = 'corruption-pair'
				for (let position = 1; position <= 2; position++) {
					const marker = document.createElement('div')
					marker.id = `corruption-${playerId}-${slot * 10 + position}`
					marker.className = 'corruption-marker'
					pair.appendChild(marker)
				}
				slots.appendChild(pair)
			}
			for (const card of gamedatas.corruptedCards?.[playerId] ?? []) {
				this.updateCorruptionMarker(card)
			}
		})

	}

	private updateCorruptionMarker(card: SanCard) {
		const previous = this.corruptedCardPositions.get(card.id)
		if (previous) {
			previous.textContent = ''
			this.corruptedCardPositions.delete(card.id)
		}
		if (card.location?.startsWith('corr_')) {
			const marker = document.getElementById(`corruption-${card.location.substring(5)}-${card.location_arg}`)
			if (marker) {
				const ownerId = Number(card.location.substring(5))
				const perspectiveId = this.getPlayerId() > 0 ? this.getPlayerId() : this.gamedatas.playerOrderWorkingWithSpectators[0]
				marker.textContent = ownerId === perspectiveId ? '✓ −1' : '✓ +1'
				this.corruptedCardPositions.set(card.id, marker)
			}
		}
	}

	private setupTooltips() {
		//todo change counter names
		this.setTooltipToClass('revealed-tokens-back-counter', _('counter1 tooltip'))

		this.setTooltipToClass('player-turn-order', _('First player'))
	}

	private setupPlayer(player: SanPlayer) {
		document.getElementById(`overall_player_board_${player.id}`)!.dataset.playerColor = player.color
		this.setupMiniPlayerBoard(player)
		this.playerTables[player.id] = new PlayerTable(
			this,
			player,
			Number(player.id) === this.getPlayerId() ? this.gamedatas.hand : []
		)
	}

	private setupMiniPlayerBoard(player: SanPlayer) {
		const playerId = Number(player.id)
		this.bga.playerPanels.getElement(playerId).insertAdjacentHTML(
			'afterbegin',
			`<div id="counters-${player.id}" class="counters">
				<div id="propaganda-counter-${player.id}-wrapper" class="counter propaganda-counter">
					<div class="fa fa-bullhorn"></div>
					<span id="propaganda-player-counter-${player.id}"></span>
				</div>

				<div id="tickets-counter-${player.id}-wrapper" class="counter tickets-counter">
					<div class="icon expTicket"></div> 
					<span id="tickets-player-counter-${player.id}"></span>
				</div>
			
				<div id="hand-cards-counter-${player.id}-wrapper" class="counter hand-cards-counter counter-left-part">
					<div class="fa fa-hand-paper-o"></div> 
					<span id="hand-cards-counter-${player.id}"></span>
				</div>
			</div>
			<div id="additional-info-${player.id}" class="counters additional-info">
				<div id="additional-icons-${player.id}" class="additional-icons"></div> 
			</div>
			`
		)

		/* const revealedTokensBackCounter = new ebg.counter();
            revealedTokensBackCounter.create(`revealed-tokens-back-counter-${player.id}`);
            revealedTokensBackCounter.setValue(player.revealedTokensBackCount);
            this.revealedTokensBackCounters[playerId] = revealedTokensBackCounter;
*/
		const propagandaCounter = new ebg.counter()
		propagandaCounter.create(`propaganda-player-counter-${player.id}`, {
			playerCounter: 'propaganda',
			playerId
		})
		this.propagandaCounters[playerId] = propagandaCounter

		const ticketsCounter = new ebg.counter()
		ticketsCounter.create(`tickets-player-counter-${player.id}`, {
			value: player.tickets,
			playerCounter: 'tickets',
			playerId: playerId
		})
		this.ticketsCounters[playerId] = ticketsCounter

		const cardsCounter = new ebg.counter()
		cardsCounter.create(`hand-cards-counter-${player.id}`)
		cardsCounter.setValue(player.cardsCount)
		this.handCardsCounters[playerId] = cardsCounter

		if (this.gameFeatures.showPlayerHelp && this.getPlayerId() === playerId) {
			//help
			dojo.place(`<div id="player-help" class="css-icon cstm-help-icon">?</div>`, `additional-icons-${player.id}`)
		}

		if (this.gameFeatures.showFirstPlayer && player.playerNo === 1) {
			dojo.place(
				`<div id="firstPlayerIcon" class="css-icon player-turn-order">1<span class="exponent">st<span></div>`,
				`additional-icons-${player.id}`,
				`last`
			)
		}

		if (this.gameFeatures.spyOnOtherPlayerBoard && this.getPlayerId() !== playerId) {
			//spy on other player
			dojo.place(
				`
            <div class="show-player-tableau"><a href="#anchor-player-${player.id}" classes="inherit-color">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 85.333343 145.79321">
                    <path fill="currentColor" d="M 1.6,144.19321 C 0.72,143.31321 0,141.90343 0,141.06039 0,140.21734 5.019,125.35234 11.15333,108.02704 L 22.30665,76.526514 14.626511,68.826524 C 8.70498,62.889705 6.45637,59.468243 4.80652,53.884537 0.057,37.810464 3.28288,23.775161 14.266011,12.727735 23.2699,3.6711383 31.24961,0.09115725 42.633001,0.00129225 c 15.633879,-0.123414 29.7242,8.60107205 36.66277,22.70098475 8.00349,16.263927 4.02641,36.419057 -9.54327,48.363567 l -6.09937,5.36888 10.8401,30.526466 c 5.96206,16.78955 10.84011,32.03102 10.84011,33.86992 0,1.8389 -0.94908,3.70766 -2.10905,4.15278 -1.15998,0.44513 -19.63998,0.80932 -41.06667,0.80932 -28.52259,0 -39.386191,-0.42858 -40.557621,-1.6 z M 58.000011,54.483815 c 3.66666,-1.775301 9.06666,-5.706124 11.99999,-8.735161 l 5.33334,-5.507342 -6.66667,-6.09345 C 59.791321,26.035633 53.218971,23.191944 43.2618,23.15582 33.50202,23.12041 24.44122,27.164681 16.83985,34.94919 c -4.926849,5.045548 -5.023849,5.323672 -2.956989,8.478106 3.741259,5.709878 15.032709,12.667218 24.11715,14.860013 4.67992,1.129637 13.130429,-0.477436 20,-3.803494 z m -22.33337,-2.130758 c -2.8907,-1.683676 -6.3333,-8.148479 -6.3333,-11.893186 0,-11.58942 14.57544,-17.629692 22.76923,-9.435897 8.41012,8.410121 2.7035,22.821681 -9,22.728685 -2.80641,-0.0223 -6.15258,-0.652121 -7.43593,-1.399602 z m 14.6667,-6.075289 c 3.72801,-4.100734 3.78941,-7.121364 0.23656,-11.638085 -2.025061,-2.574448 -3.9845,-3.513145 -7.33333,-3.513145 -10.93129,0 -13.70837,13.126529 -3.90323,18.44946 3.50764,1.904196 7.30574,0.765377 11,-3.29823 z m -11.36999,0.106494 c -3.74071,-2.620092 -4.07008,-7.297494 -0.44716,-6.350078 3.2022,0.837394 4.87543,-1.760912 2.76868,-4.29939 -1.34051,-1.615208 -1.02878,-1.94159 1.85447,-1.94159 4.67573,0 8.31873,5.36324 6.2582,9.213366 -1.21644,2.27295 -5.30653,5.453301 -7.0132,5.453301 -0.25171,0 -1.79115,-0.934022 -3.42099,-2.075605 z"></path>
                </svg>
                </a>
            </div>
            `,
				`additional-icons-${player.id}`
			)
		}
	}

	private setupHelpPopin() {
		new HelpManager(this, {
			buttons: [
				new BgaHelpPopinButton({
					title: _('Roles in play'),
					html: this.getHelpHtml(),
					buttonBackground: 'white',
					buttonColor: '#266059'
				}),
				new BgaHelpExpandableButton({
					unfoldedHtml: `<div id="player-help-visible-wrapper" >
										<div id="player-help-visible" class="player-help-visible" style="margin: 5px;" data-player-color="${
											this.getCurrentPlayer()?.color ?? 'fff'
										}"></div>
									</div>`,
					//foldedHtml: `?`,
					expandedWidth: '250px',
					expandedHeight: '182px',
					expandedRadius: '3%',
					foldedContentExtraClasses: 'button-help-expandable'
				})
			]
		})
	}

	private getHelpHtml() {
		let html = `
        <div id="help-popin"> `
		/*new Set(this.gamedatas.rolesInPlay).forEach((r) => {
			html += this.getRoleHtml(r, this.gamedatas.rolesInPlay.filter((allR) => allR === r).length)
		})*/
		html += `
        </div>
        `
		return html
	}

	/* This enable to inject translatable styled things to logs or action bar */
	/* @Override */
	public bgaFormatText(log: string, args: any): { log: string; args: any } {
		try {
			if (log && args && !args.processed) {
				args.processed = true

				//displays gems
				;['gemType'].forEach((field) => {
					if (typeof args[field] === 'number') {
						args[field] = `<span class="log-icon gem gem-${args[field]}"></span>`
					}
				})
			}
		} catch (e) {
			console.error(log, args, 'Exception thrown', e.stack)
		}
		return { log, args }
	}

	public customPreferenceChanged(prefId: number, prefValue: any): void {
		switch (prefId) {
			case 100:
				if (this.isCustomSoundsOn()) {
					this.bga.sounds.preloadSounds(this.customSounds)
				}
				break
		}
	}

	///////////////////////////////////////////////////
	//// Game & client states
	//

	public onEnteringState(stateName: string, args: any) {
		log('Entering state: ' + stateName, args)

		if (this.gameFeatures.spyOnActivePlayerInGeneralActions) {
			this.addArrowsToActivePlayer(args)
		}
	}

	/**
	 * Clear the last-turn banner when scoring begins.
	 */
	public onEnteringEndScore() {
		this.bga.gameArea.removeLastTurnBanner()
	}
	///////////////////////////////////////////////////
	//// Utility methods
	///////////////////////////////////////////////////

	private getSelectedIdsAsParam(stock: CardStock<SanCard>) {
		return stock
			.getSelection()
			.map((c) => c.id)
			.join(',')
	}

	public isRealTime() {
		return this.gameui.bRealtime
	}

	public closeCurrentTooltip() {
		if (this.displayedTooltip == null) return
		else {
			this.displayedTooltip.close()
			this.displayedTooltip = null
		}
	}

	public addTooltipOnClickHelpButton(id, html, delay) {
		/*let tooltip = new dijit.Tooltip({
			label: html,
			showDelay: delay
		})

		dojo.connect($(id), 'click', (evt) => {
			evt.stopPropagation()

			if (tooltip.state == 'SHOWING') {
				this.closeCurrentTooltip()
			} else {
				this.closeCurrentTooltip()
				tooltip.open($(id))
				this.displayedTooltip = tooltip
			}
		})

		dojo.connect($(id), 'mouseleave', () => {
			tooltip.close()
		})*/
	}

	public dontPreloadUselessAssets() {
		if (this.getPlayersCount() == 1) {
			//this.bga.images.dontPreloadImage('centralBoard.png')//TODO
		} else {
			//this.bga.images.dontPreloadImage('centralBoardSolo.png')
		}
	}

	public toggleActionButtonAbility(buttonId: string, enable: boolean, autoClickIfEnabled: boolean | null = null) {
		if (autoClickIfEnabled == null) {
			//autoClickIfEnabled= this.isConfirmOnlyOnPlacingTokensOn()
		}
		dojo.toggleClass(buttonId, 'disabled', !enable)
		if (autoClickIfEnabled && !dojo.hasClass(buttonId, 'disabled')) {
			$(buttonId).click()
		}
	}

	public resetClientActionData() {
		this.clientActionData = {
			placedCardId: null,
			destinationSquare: null,
			previousCardParentInHand: null
		}
	}

	public handSelectionChange(selection: SanCard[], lastChange: SanCard): void {
		if (this.bga.players.isCurrentPlayerActive()) {
			this.toggleActionButtonVisibility('btn-validate', selection.length > 0)
		}
	}

	///////////////////////////////////////////////////
	//// Player's action

	/*
    
        Here, you are defining methods to handle player's action (ex: results of mouse click on 
        game objects).
        
        Most of the time, these methods:
        _ check the action is possible at this game state.
        _ make a call to the game server
    
    */
	private ensureStockSelection(stocks: CardStock<SanCard>[], errorMsg: string, callback: Function) {
		if (stocks.every((s) => s.getSelection().length > 0)) {
			callback()
		} else {
			this.bga.dialogs.showMessage(errorMsg, 'error')
		}
	}

	///////////////////////////////////////////////////
	//// Reaction to cometD notifications

	/*
        setupNotifications:
        
        In this method, you associate each of your game notifications with your local method to handle it.
        
        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your san.game.php file.
    
    */
	setupNotifications() {
		log('notifications subscriptions setup')

		// TODO: here, associate your game notifications with local methods

		// Example 1: standard notification handling
		// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

		// Example 2: standard notification handling + tell the user interface to wait
		//            during 3 seconds after calling the method in order to let the players
		//            see what is happening in the game.
		// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
		// this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
		//

		const notifs = [
			['materialMove', ANIMATION_MS],
			['lastTurn', 1],
			['importantMessage', 3000]
		]

		notifs.forEach((notif) => {
			dojo.subscribe(notif[0], this, `notif_${notif[0]}`)
			//comment to prevent formating to glue these 2 lines
			;(this.gameui as any).notifqueue.setSynchronous(notif[0], notif[1])
		})
	}

	notif_materialMove(notif: Notif<NotifMaterialMove>) {
		log('notif_materialMove', notif)
		for (const card of notif.args.material as SanCard[]) {
			this.updateCorruptionMarker(card)
			if (card.location?.startsWith('corr_')) {
				this.cardsManager.removeCard(card)
			} else if (card.location?.startsWith('virus_')) {
				this.virusZone.decks[Number(card.location.substring(6))]?.addCard(card)
			} else if (card.location === 'river') {
				this.river.addCard(card)
			} else if (card.location?.startsWith('hand_')) {
				const playerId = Number(card.location.substring(5))
				this.playerTables[playerId]?.handStocks[card.type_arg]?.addCard(card)
			}
		}
		/*switch (notif.args.type) {
			case "MISSION":
				const cards = notif.args.material as Array<MissionCard>
				this.notif_missionMove(cards, notif)
				break
			default:
				console.error('Material type move not handled', notif)
				break
		}*/
	}

	/* notif_missionMove(cards: MissionCard[], notif: Notif<NotifMaterialMove>) {
		const card = cards.at(0)
		switch (notif.args.to) {
			case "DISCARD":
				if (notif.args.fromArg == notif.args.toArg) {
					this.festivalStocks[notif.args.toArg].flipCard(card)
					if (notif.args?.soldOut) this.playCustomSound('clap', false)
				} else {
					this.festivalStocks[notif.args.toArg].addCard(card)
				}
				break

			default:
				console.error('Festival move destination not handled', notif)
				break
		}
	}*/
}
