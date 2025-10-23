// assets/react/components/dtDatePresets.js
import moment from 'moment';

export function computePreset(key) {
  const now = moment();
  switch (key) {
    case 'last24h': return { start: moment(now).subtract(24, 'hours'), end: moment(now) };
    case 'last7d':  return { start: moment(now).subtract(7,  'days'),  end: moment(now) };
    case 'last30d': return { start: moment(now).subtract(30, 'days'),  end: moment(now) };
    case 'thisWeek':return { start: moment(now).startOf('week'),       end: moment(now).endOf('week') };
    case 'lastWeek':{
      const s = moment(now).subtract(1,'week').startOf('week');
      return { start: s, end: moment(s).endOf('week') };
    }
    default:        return { start: moment(now).startOf('day'),        end: moment(now).endOf('day') };
  }
}

export function applyPresetToActiveSB(presetKey) {
  const { start, end } = computePreset(presetKey);
  const $input = $('.dtsb-value .dt-sb-daterange:visible').first();
  if ($input.length === 0) return false;
  const dr = $input.data('daterangepicker');
  if (!dr) return false;
  dr.setStartDate(start); dr.setEndDate(end);
  $input.trigger('apply.daterangepicker', { startDate: start, endDate: end });
  return true;
}
