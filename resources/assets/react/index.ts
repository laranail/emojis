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
 * it never mounts anything on its own. Marked "use client" for React Server Components frameworks.
 */

export { EmojiPicker, type EmojiPickerProps } from './EmojiPicker.js';
export { useEmojiPicker, type EmojiPickerState, type UseEmojiPickerOptions } from './useEmojiPicker.js';
// The pure building blocks, for a picker UI of your own over useEmojiPicker(). This bundle has no auto-init,
// so importing them here mounts nothing — unlike importing the vanilla module.
export {
  ApiSource,
  StaticSource,
  localStorageStore,
  memoryStore,
  DEFAULT_STRINGS,
  MAX_RESULTS,
  TONE_SWATCHES,
  buildSections,
  capPayload,
  charOf,
  clampColumns,
  clampTone,
  customCode,
  gridTarget,
  insertText,
  readRecent,
  resultText,
  rovingIndex,
  search,
  searchCustom,
  searchResults,
  withTone,
  type Insertable,
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
} from '../scripts/picker.js';
