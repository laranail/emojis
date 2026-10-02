// A small payload in the shape PayloadBuilder writes.
export const payload = () => ({
  dataset: 'test',
  locale: 'en',
  groups: [
    {
      slug: 'smileys_and_emotion',
      label: 'Smileys & Emotion',
      emoji: [
        { emoji: '😀', hexcode: '1F600', name: 'grinning face', shortcode: 'grinning', keywords: ['face', 'grin'], version: '1.0', skins: {} },
        { emoji: '😂', hexcode: '1F602', name: 'face with tears of joy', shortcode: 'joy', keywords: ['face', 'joy', 'laugh'], version: '0.6', skins: {} },
        { emoji: '🫠', hexcode: '1FAE0', name: 'melting face', shortcode: 'melting_face', keywords: ['melt'], version: '14.0', skins: {} },
      ],
    },
    {
      slug: 'people_and_body',
      label: 'People & Body',
      emoji: [
        { emoji: '👋', hexcode: '1F44B', name: 'waving hand', shortcode: 'wave', keywords: ['hand', 'wave'], version: '0.6', skins: { 1: '1F44B-1F3FB', 2: '1F44B-1F3FC', 3: '1F44B-1F3FD', 4: '1F44B-1F3FE', 5: '1F44B-1F3FF' } },
        { emoji: '🤝', hexcode: '1F91D', name: 'handshake', shortcode: 'handshake', keywords: ['agreement'], version: '3.0', skins: { '3-3': '1F91D-1F3FD' } },
      ],
    },
    {
      slug: 'travel_and_places',
      label: 'Travel & Places',
      emoji: [{ emoji: '🚀', hexcode: '1F680', name: 'fusée', shortcode: 'rocket', keywords: ['espace'], version: '0.6', skins: {} }],
    },
  ],
  custom: [{ name: 'partyparrot', label: 'Party parrot', image: 'data:image/png;base64,iVBORw0KGgo=', fallback: null }],
});
