/*!
 * laranail/emojis picker for React.
 *
 *   import { EmojiPicker, ApiSource } from '@laranail/emojis-picker/react';
 *   import '@laranail/emojis-picker/styles.css';
 *
 *   const source = new ApiSource('/laranail/emojis/api/v1');   // create once, outside render
 *   <EmojiPicker source={source} target={textareaRef} onSelect={({ emoji }) => …} />
 *
 * The state is the same pure functions the vanilla picker uses (resources/assets/scripts/picker.ts), bundled
 * in; React is a peer dependency. The vanilla picker's auto-init is compiled out of this bundle, so importing
 * it never mounts anything on its own.
 */

export { EmojiPicker, type EmojiPickerProps } from './EmojiPicker';
export { useEmojiPicker, type EmojiPickerState, type UseEmojiPickerOptions } from './useEmojiPicker';
export {
  ApiSource,
  StaticSource,
  localStorageStore,
  memoryStore,
  DEFAULT_STRINGS,
  type PickerCustom,
  type PickerEmoji,
  type PickerGroup,
  type PickerPayload,
  type PickerSection,
  type PickerSource,
  type PickerStore,
  type PickerStrings,
  type RecentOrder,
  type SelectDetail,
  type SortMode,
} from '../scripts/picker';
