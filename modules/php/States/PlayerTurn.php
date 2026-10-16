<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\San\Game;
use Bga\Games\San\SanCard;
use Constants;

class PlayerTurn extends GameState {

    function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_PLAYER_TURN,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate(_('${actplayer} must play cards from his hand')),
            descriptionMyTurn: clienttranslate('${you} must play cards from your hand'),
        );
    }

    function onEnteringState(int $activePlayerId, array $args) {
        $pending = $this->game->globals->get(Constants::GLB_CARD_TO_AUTO_PLAY);
        if ($pending === null) {
            return;
        }
        $this->game->globals->delete(Constants::GLB_CARD_TO_AUTO_PLAY);
        $nextState = $this->actPlayCard($pending['cardId'], $pending['choice'], $activePlayerId, [
            'selectableHandCards' => $this->game->cardManager->getPlayedCards($activePlayerId, true),
        ]);
        if ($nextState === ImmediateAction::class) {
            $this->game->globals->set(Constants::GLB_CURRENT_CARD, $this->game->cardManager->getCard($pending['cardId']));
        }
        return $nextState;
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        // Get some values from the current game situation from the database.
        $hasPlayed = !empty($this->game->cardManager->getPlayedCards($activePlayerId));
        return [
            "canPass" => $hasPlayed,
            "propagandaCost" => $this->game->getPropagandaCost($activePlayerId),
            "canResetTurn" => $hasPlayed,
            "selectableHandCards" => $this->getPossibleCards($activePlayerId),
        ];
    }

    #[PossibleAction]
    public function actPlayAll(#[IntParam(min: Constants::CARD_TYPE_CORRUPTION, max: Constants::CARD_TYPE_VIRUS)] int $typeArg, int $activePlayerId, array $args) {
        if ($typeArg === Constants::CARD_TYPE_HARDWARE) {
            throw new UserException(clienttranslate('You cannot play this type of card all at once'));
        }
        $cards = array_filter(
            $args['selectableHandCards'],
            fn($card) =>
            $card->type_arg === $typeArg && !$card->chooseOne && !$this->game->cardManager->hasImmediateAction($card)
        );
        if (empty($cards)) {
            throw new UserException(clienttranslate('You cannot play this card'));
        }
        $cards = array_reverse($cards);//in the same order as the UI to make the animation smooth
        foreach ($cards as $card) {
            $this->actPlayCard($card->id, 0, $activePlayerId, $this->getArgs($activePlayerId));
        }
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayCard(int $cardId, #[IntParam(min: 0, max: 3)] ?int $choice, int $activePlayerId, array $args) {
        /** @var SanCard|null $sanCard */
        $sanCard = null;
        foreach ($args['selectableHandCards'] as $card) {
            if ($card->id === $cardId) {
                $sanCard = $card;
                break;
            }
        }
        if ($sanCard === null) {
            throw new UserException(clienttranslate('You cannot play this card'));
        }
        if ($sanCard->chooseOne && !$choice) {
            throw new UserException(clienttranslate('You must choose which option to play'));
        }
        $this->game->cardManager->playCard($sanCard, $choice, $activePlayerId);
        if ($this->game->cardManager->hasImmediateAction($sanCard)) {
            $this->game->globals->set(Constants::GLB_CURRENT_CARD, $sanCard);
            if ($sanCard->destroyCards > 0) {
                $this->game->globals->set(Constants::GLBL_REMAINING_DESTROYS, $card->destroyCards);
            }
            return ImmediateAction::class;
        }
        return PlayerTurn::class;
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player pass, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     */
    #[PossibleAction]
    public function actPass(int $activePlayerId) {
        $this->game->cardManager->revealPlayedCards($activePlayerId);
        $notificationArgs = [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
        ];
        foreach (['propaganda', 'hacking', 'corruption', 'income'] as $counter) {
            $value = $this->game->{$counter . 'Counter'}->get($activePlayerId);
            $notificationArgs[$counter . 'Icon'] = $value === 0 ? '' : $counter;
            $notificationArgs[$counter] = $value === 0 ? '' : $value;
        }
        $this->game->notify->all('msg', clienttranslate('Summary of the turn: ${propagandaIcon}${propaganda}${hackingIcon}${hacking}${corruptionIcon}${corruption}${incomeIcon}${income}'), $notificationArgs);
        return PlayerDecisions::class;

        /*  $end = $this->game->hasReachedEndOfGameRequirements();
        if ($end) {
            return CardShopping::class;
        } else {
            return NextPlayer::class;
        }*/
    }

    #[PossibleAction]
    function actResetPlayerTurn(int $activePlayerId) {
        $contexts = [];
        foreach ($this->game->contextManager->getAllContextLogs('playCard') as $context) {
            if ((int) $context['player'] === $activePlayerId && !$context['resolved']) {
                $contexts[(int) $context['param1']] ??= $context;
            }
        }

        $returnedIds = [];
        $hand = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $activePlayerId);
        $copies = $this->game->globals->get(Constants::GLB_COPIED_PLAYED_CARDS, []);
        $sources = $this->game->globals->get(Constants::GLB_COPIED_PLAYED_CARD_SOURCES, []);
        $playedCards = $this->game->cardManager->getPlayedCards($activePlayerId);
        $cancelable = $this->game->cardManager->getCancelablePlayedCards($activePlayerId);

        foreach ($playedCards as $card) {
            if (!isset($cancelable[$card->id])) {
                continue;
            }
            $context = $contexts[$card->id] ?? null;

            $this->game->cardManager->moveCardToLocation($card, $hand, $activePlayerId, false);
            $from = $card->location;
            $fromArg = $card->location_arg;
            $card->location = $hand;
            $card->location_arg = $activePlayerId;
            $this->game->notify->player($activePlayerId, 'materialMove', '', [
                'type' => Constants::MATERIAL_TYPE_CARD,
                'from' => $from,
                'fromArg' => $fromArg,
                'to' => $hand,
                'toArg' => $activePlayerId,
                'material' => [$card],
            ]);
            $returnedIds[] = $card->id;
            unset($copies[$card->id], $sources[$card->id]);
            if ($context !== null) {
                $this->game->contextManager->deleteContextLog((int) $context['id']);
            }
        }

        $this->game->globals->set(Constants::GLB_COPIED_PLAYED_CARDS, $copies);
        $this->game->globals->set(Constants::GLB_COPIED_PLAYED_CARD_SOURCES, $sources);
        $this->game->cardManager->recalculateCounters($activePlayerId);
        $revealed = $this->game->globals->get('revealedPlayedCards', []);
        $this->game->globals->set('revealedPlayedCards', array_values(array_diff($revealed, $returnedIds)));
        return PlayerTurn::class;
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: play a random card).
     * 
     * See more about Zombie Mode: https://en.doc.boardgamearena.com/Zombie_Mode
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, 
     * but use the $playerId passed in parameter and $this->game->getPlayerNameById($playerId) instead.
     */
    function zombie(int $playerId) {
        //plays the cards type that will give the most symbols
        $args = $this->getArgs($playerId);
        $symbols = [
            Constants::CARD_TYPE_PROPAGANDA => ['propaganda', 1],
            Constants::CARD_TYPE_HACKING => ['hacking', 2],
            Constants::CARD_TYPE_CORRUPTION => ['corruption', 3],
        ];
        $playedCards = $this->game->cardManager->getPlayedCards($playerId, true);
        $bestType = null;
        foreach ($playedCards as $card) {
            if (isset($symbols[$card->type_arg])) {
                $bestType = $card->type_arg;
                break;
            }
        }
        if ($bestType === null) {
            $scores = [
                Constants::CARD_TYPE_PROPAGANDA => $this->game->propagandaCounter->get($playerId),
                Constants::CARD_TYPE_HACKING => $this->game->hackingCounter->get($playerId),
                Constants::CARD_TYPE_CORRUPTION => $this->game->corruptionCounter->get($playerId),
            ];
            $typeCounts = array_count_values(array_column($args['selectableHandCards'], 'type_arg'));
            foreach ($args['selectableHandCards'] as $card) {
                $perCardType = match ($card->specialEffect) {
                    Constants::SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD => Constants::CARD_TYPE_PROPAGANDA,
                    Constants::SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD => Constants::CARD_TYPE_HACKING,
                    Constants::SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD => Constants::CARD_TYPE_CORRUPTION,
                    default => null,
                };
                foreach ($symbols as $type => [$property]) {
                    if (!isset($symbols[$card->type_arg]) || $card->type_arg === $type) {
                        $scores[$type] += $perCardType === $type ? $typeCounts[$card->type_arg] : $card->$property;
                    }
                }
            }
            $bestType = array_search(max($scores), $scores, true);
        }

        while (!empty($args['selectableHandCards'])) {
            $cardToPlay = null;
            foreach ($args['selectableHandCards'] as $card) {
                if (isset($symbols[$card->type_arg]) && $card->type_arg !== $bestType) {
                    continue;
                }
                if ($card->type_arg === Constants::CARD_TYPE_HARDWARE) {
                    $cardToPlay = $card;
                    break;
                }
                if ($cardToPlay === null) {
                    $cardToPlay = $card;
                }
            }
            if ($cardToPlay === null) {
                return $this->actPass($playerId);
            }
            $choice = 0;
            if ($cardToPlay->chooseOne) {
                $choice = $cardToPlay->type_arg === Constants::CARD_TYPE_HARDWARE ? $symbols[$bestType][1] : 1;
            }
            $nextState = $this->actPlayCard($cardToPlay->id, $choice, $playerId, $args);
            if ($nextState !== PlayerTurn::class) {
                return $nextState;
            }
            $args = $this->getArgs($playerId);
        }
        return $this->actPass($playerId);
    }

    function getPossibleCards(int $activePlayerId) {
        $hand = $this->game->cardManager->getPlayerHand($activePlayerId);
        $playedCards = $this->game->cardManager->getPlayedCards($activePlayerId, true);
        foreach ($playedCards as $card) {
            if ($card->specialEffect === Constants::SPECIAL_EFFECT_PLAY_ALL_CARD_TYPES) {
                return $hand;
            }
        }
        $playedTypes = array_column($playedCards, 'type_arg');
        //only one type is allowed from Hacking, Corruption or Propaganda. All other types are allowed
        $restrictedTypes = [
            Constants::CARD_TYPE_HACKING => true,
            Constants::CARD_TYPE_CORRUPTION => true,
            Constants::CARD_TYPE_PROPAGANDA => true,
        ];

        $playedRestrictedType = null;
        foreach ($playedTypes as $typeArg) {
            if (isset($restrictedTypes[$typeArg])) {
                $playedRestrictedType = $typeArg;
                break;
            }
        }

        if ($playedRestrictedType === null) {
            return $hand;
        }

        return array_values(array_filter(
            $hand,
            fn($card) => !isset($restrictedTypes[$card->type_arg]) || $card->type_arg === $playedRestrictedType
        ));
    }
}
