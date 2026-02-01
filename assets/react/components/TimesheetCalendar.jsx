import React, { useEffect, useMemo, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin, { Draggable } from '@fullcalendar/interaction';

const toYmd = (date) => {
  const d = new Date(date);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const parseTime = (value) => {
  const [h, m] = String(value).split(':').map((part) => parseInt(part, 10));
  if (Number.isNaN(h) || Number.isNaN(m)) return 0;
  return h * 60 + m;
};

const sumHours = (ranges) => {
  const totalMinutes = ranges.reduce((acc, range) => {
    const [start, end] = range;
    const minutes = Math.max(0, parseTime(end) - parseTime(start));
    return acc + minutes;
  }, 0);
  return (totalMinutes / 60).toFixed(2);
};

const formatRanges = (ranges) => ranges.map(([start, end]) => `${start}-${end}`).join(' / ');

const buildEventTitle = (template, hoursValue) => `${template.label} (${hoursValue}h)`;

const buildComment = (template) => `${template.label} [${formatRanges(template.ranges)}]`;

const TimesheetCalendar = ({ contracts, initialHours }) => {
  const templates = useMemo(
    () => [
      {
        id: 'homeoffice',
        label: 'HomeOffice',
        ranges: [
          ['08:45', '12:00'],
          ['12:45', '18:00'],
        ],
        color: '#0d6efd',
      },
    ],
    []
  );

  const [selectedContractId, setSelectedContractId] = useState(
    contracts?.[0]?.id ? String(contracts[0].id) : ''
  );

  const [events, setEvents] = useState(() =>
    (initialHours || []).map((entry) => ({
      id: String(entry.id),
      title: `${entry.hours}h — ${entry.contractLabel}${entry.comment ? ` · ${entry.comment}` : ''}`,
      start: entry.workDate,
      allDay: true,
      backgroundColor: '#198754',
      borderColor: '#198754',
      extendedProps: {
        contractId: entry.contractId,
        hours: entry.hours,
        comment: entry.comment || '',
      },
    }))
  );

  const listRef = useRef(null);

  useEffect(() => {
    if (!listRef.current) return undefined;

    const draggable = new Draggable(listRef.current, {
      itemSelector: '.ts-template-item',
      eventData: (eventEl) => {
        const templateId = eventEl.getAttribute('data-template-id');
        const template = templates.find((item) => item.id === templateId);
        if (!template) return null;
        const hoursValue = sumHours(template.ranges);
        return {
          title: buildEventTitle(template, hoursValue),
          allDay: true,
          backgroundColor: template.color,
          borderColor: template.color,
          extendedProps: {
            templateId: template.id,
            hours: hoursValue,
            comment: buildComment(template),
          },
        };
      },
    });

    return () => {
      draggable.destroy();
    };
  }, [templates]);

  const handleEventReceive = async (info) => {
    const contractId = selectedContractId;
    if (!contractId) {
      info.event.remove();
      alert('Select a contract before dropping hours.');
      return;
    }

    const template = templates.find((item) => item.id === info.event.extendedProps.templateId);
    if (!template) {
      info.event.remove();
      return;
    }

    const workDate = toYmd(info.event.start || new Date());
    const hoursValue = sumHours(template.ranges);
    const commentValue = buildComment(template);

    info.event.setProp('title', buildEventTitle(template, hoursValue));
    info.event.setExtendedProp('hours', hoursValue);
    info.event.setExtendedProp('comment', commentValue);

    const payload = new URLSearchParams({
      contractId,
      workDate,
      hours: hoursValue,
      comment: commentValue,
    });

    try {
      const response = await fetch('/timesheet/hours', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString(),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const contract = contracts.find((item) => String(item.id) === String(contractId));
      const contractLabel = contract?.label || 'Contract';
      setEvents((prev) => [
        ...prev,
        {
          id: `new-${Date.now()}`,
          title: `${hoursValue}h — ${contractLabel} · ${commentValue}`,
          start: workDate,
          allDay: true,
          backgroundColor: template.color,
          borderColor: template.color,
          extendedProps: {
            contractId,
            hours: hoursValue,
            comment: commentValue,
          },
        },
      ]);
    } catch (error) {
      info.event.remove();
      alert('Failed to save hours.');
    }
  };

  return (
    <div className="row g-3">
      <div className="col-12 col-lg-3">
        <div className="border rounded p-3 h-100">
          <div className="fw-semibold mb-2">Templates</div>
          <div ref={listRef} className="d-flex flex-column gap-2">
            {templates.map((template) => {
              const hoursValue = sumHours(template.ranges);
              return (
                <div
                  key={template.id}
                  className="ts-template-item border rounded p-2 bg-light"
                  data-template-id={template.id}
                  style={{ cursor: 'grab' }}
                >
                  <div className="fw-semibold">{template.label}</div>
                  <div className="text-muted small">{formatRanges(template.ranges)}</div>
                  <div className="small">{hoursValue}h</div>
                </div>
              );
            })}
          </div>
          <hr />
          <label className="form-label">Contract</label>
          <select
            className="form-select"
            value={selectedContractId}
            onChange={(event) => setSelectedContractId(event.target.value)}
          >
            <option value="">Select contract</option>
            {contracts.map((contract) => (
              <option key={contract.id} value={contract.id}>
                {contract.label}
              </option>
            ))}
          </select>
          <div className="form-text">Drag a template onto the calendar.</div>
        </div>
      </div>
      <div className="col-12 col-lg-9">
        <FullCalendar
          plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
          initialView="dayGridMonth"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
          }}
          height="auto"
          editable={false}
          droppable
          eventReceive={handleEventReceive}
          events={events}
        />
      </div>
    </div>
  );
};

export default TimesheetCalendar;
