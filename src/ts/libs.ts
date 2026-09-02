import type { BgaAnimations as BgaAnimationsType, Manager as AnimationManagerType } from '../../bga-animations'
import type { BgaCards as BgaCardsType } from '../../bga-cards'
import type { BgaAutofit as BgaAutofitType } from '../../bga-autofit'

const BgaAnimations: typeof BgaAnimationsType = await globalThis.importEsmLib('bga-animations', '1.x')
const BgaCards: typeof BgaCardsType = await globalThis.importEsmLib('bga-cards', '1.x')
const BgaAutofit: typeof BgaAutofitType = await globalThis.importEsmLib('bga-autofit', '1.x')

export { BgaAnimations, BgaCards, BgaAutofit }
