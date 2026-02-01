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

const buildEventTitle = (template, range) => `${template.label} ${range[0]}-${range[1]}`;

const buildComment = (template) => `${template.label}`;

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
    (initialHours || []).map((entry) => {
      const hasTime = entry.startTime && entry.endTime;
      const start = hasTime ? `${entry.workDate}T${entry.startTime}` : entry.workDate;
      const end = hasTime ? `${entry.workDate}T${entry.endTime}` : undefined;
      return {
        id: String(entry.id),
        title: hasTime
          ? `${entry.contractLabel} · ${entry.startTime}-${entry.endTime}`
          : `${entry.hours}h — ${entry.contractLabel}${entry.comment ? ` · ${entry.comment}` : ''}`,
        start,
        end,
        allDay: !hasTime,
        backgroundColor: '#198754',
        borderColor: '#198754',
        extendedProps: {
          contractId: entry.contractId,
          contractLabel: entry.contractLabel,
          hours: entry.hours,
          comment: entry.comment || '',
        },
      };
    })
  );

  const listRef = useRef(null);
  const trashRef = useRef(null);

  useEffect(() => {
    if (!listRef.current) return undefined;

    const draggable = new Draggable(listRef.current, {
      itemSelector: '.ts-template-item',
      eventData: (eventEl) => {
        const templateId = eventEl.getAttribute('data-template-id');
        const template = templates.find((item) => item.id === templateId);
        if (!template) return null;
        return {
          allDay: true,
          backgroundColor: template.color,
          borderColor: template.color,
          extendedProps: {
            templateId: template.id,
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
    info.event.remove();

    const workDate = toYmd(info.event.start || new Date());
    const commentValue = buildComment(template);

    try {
      const responses = await Promise.all(
        template.ranges.map(async (range) => {
          const payload = new URLSearchParams({
            contractId,
            workDate,
            startTime: range[0],
            endTime: range[1],
            comment: commentValue,
          });

          const response = await fetch('/timesheet/hours', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload.toString(),
          });

          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }

          return response.json();
        })
      );

      const contract = contracts.find((item) => String(item.id) === String(contractId));
      const contractLabel = contract?.label || 'Contract';

      setEvents((prev) => [
        ...prev,
        ...responses.map((entry, index) => {
          const range = template.ranges[index];
          return {
            id: String(entry.id),
            title: `${contractLabel} · ${range[0]}-${range[1]}`,
            start: `${workDate}T${range[0]}`,
            end: `${workDate}T${range[1]}`,
            allDay: false,
            backgroundColor: template.color,
            borderColor: template.color,
            extendedProps: {
              contractId,
              contractLabel,
              hours: entry.hours,
              comment: entry.comment || '',
            },
          };
        }),
      ]);
    } catch (error) {
      alert('Failed to save hours.');
    }
  };

  const handleEventChange = async (event) => {
    if (!event?.id) return;
    const start = event.start;
    const end = event.end;
    if (!start || !end) return;

    const workDate = toYmd(start);
    const startTime = `${String(start.getHours()).padStart(2, '0')}:${String(start.getMinutes()).padStart(2, '0')}`;
    const endTime = `${String(end.getHours()).padStart(2, '0')}:${String(end.getMinutes()).padStart(2, '0')}`;

    const payload = new URLSearchParams({
      workDate,
      startTime,
      endTime,
    });

    try {
      const response = await fetch(`/timesheet/hours/${encodeURIComponent(event.id)}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: payload.toString(),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const result = await response.json();
      if (result?.startTime && result?.endTime) {
        const label = event.extendedProps?.contractLabel || event.title.split(' · ')[0];
        event.setProp('title', `${label} · ${result.startTime}-${result.endTime}`);
      }
    } catch (error) {
      alert('Failed to update hours.');
    }
  };

  const handleEventDragStop = async (info) => {
    const trash = trashRef.current;
    if (!trash) return;
    const trashRect = trash.getBoundingClientRect();
    const x = info.jsEvent.clientX;
    const y = info.jsEvent.clientY;
    const inTrash = x >= trashRect.left && x <= trashRect.right && y >= trashRect.top && y <= trashRect.bottom;
    if (!inTrash) return;

    const eventId = info.event?.id;
    if (!eventId) return;

    try {
      const response = await fetch(`/timesheet/hours/${encodeURIComponent(eventId)}/delete`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      info.event.remove();
    } catch (error) {
      alert('Failed to delete hours.');
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
          <div
            ref={trashRef}
            className="border border-danger rounded text-danger text-center py-2 mt-3"
            style={{ background: '#fff5f5' }}
          >
            Drop here to delete
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
          initialView="timeGridWeek"
          headerToolbar={{
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay',
          }}
          firstDay={1}
          locale="en-CA"
          titleFormat={{ year: 'numeric', month: '2-digit', day: '2-digit' }}
          dayHeaderFormat={{ weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit' }}
          slotLabelFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }}
          eventTimeFormat={{ hour: '2-digit', minute: '2-digit', hour12: false }}
          height="auto"
          editable
          droppable
          eventReceive={handleEventReceive}
          eventDrop={(info) => handleEventChange(info.event)}
          eventResize={(info) => handleEventChange(info.event)}
          eventDragStop={handleEventDragStop}
          events={events}
          slotMinTime="06:00:00"
          slotMaxTime="21:00:00"
        />
      </div>
    </div>
  );
};

export default TimesheetCalendar;
