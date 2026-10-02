// Compiled by `npm run typecheck`, never run: proves the declarations describe the documented usage.
import { Picker, ApiSource, StaticSource, localStorageStore, type SelectDetail, type PickerPayload } from '../../resources/assets/types/picker';

declare const element: HTMLElement;
declare const textarea: HTMLTextAreaElement;
declare const payload: PickerPayload;

const picked: Promise<Picker> = Picker.create(element)
  .source(new ApiSource('/laranail/emojis/api/v1'))
  .locale('fr')
  .skinTone(3)
  .history({ max: 36, store: localStorageStore('app'), order: 'frequent' })
  .sort('newest')
  .categories(['recent', 'smileys_and_emotion'])
  .target(textarea)
  .on('select', (detail: SelectDetail) => detail.emoji.length)
  .mount();

new StaticSource(payload);
new StaticSource('laranail-emoji-picker-data');
void picked;
