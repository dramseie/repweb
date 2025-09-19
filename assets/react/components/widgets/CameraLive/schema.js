export default {
  fields: [
    { key: 'src',       label: 'HLS URL',        type: 'text',  required: true, placeholder: '/hlsdisk/sms18/index.m3u8' },
    { key: 'poster',    label: 'Poster URL',     type: 'text',  required: false },
    { key: 'muted',     label: 'Muted',          type: 'bool',  default: true },
    { key: 'autoPlay',  label: 'Autoplay',       type: 'bool',  default: true },
    { key: 'controls',  label: 'Show controls',  type: 'bool',  default: true },
    { key: 'objectFit', label: 'Fit',            type: 'select', options: ['cover','contain','fill','scale-down'], default: 'cover' },
  ],
  defaults: {
    src: '/hlsdisk/sms18/index.m3u8',
    muted: true,
    autoPlay: true,
    controls: true,
    objectFit: 'cover',
  }
};
