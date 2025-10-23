// assets/frw/components/StepPanel.jsx
import React, { useMemo } from 'react';
import FieldText from './fields/FieldText.jsx';
import FieldTextarea from './fields/FieldTextarea.jsx';
import FieldNumber from './fields/FieldNumber.jsx';
import FieldSelect from './fields/FieldSelect.jsx';
import FieldMultiSelect from './fields/FieldMultiSelect.jsx';
import FieldDate from './fields/FieldDate.jsx';

const registry = {
  text: FieldText,
  textarea: FieldTextarea,
  number: FieldNumber,
  select: FieldSelect,
  multiselect: FieldMultiSelect,
  date: FieldDate,
};

export default function StepPanel({ step, answers, onChange, onPrice, onPrev, onNext, onSubmit }) {
  const fields = step?.fields || [];
  const visibleFields = useMemo(() => fields.filter((f) => isVisible(f, answers)), [fields, answers]);

  function setValue(key, value) {
    onChange({ ...answers, [key]: value });
  }

  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-semibold">{step?.title || 'Step'}</h2>

      {/* Fields stacked vertically */}
      <div className="flex flex-col gap-4">
        {visibleFields.map((f) => {
          const Comp = registry[f.type] || FieldText;
          return (
            <div key={f.key} className="flex flex-col gap-1">
              <Comp field={f} value={answers[f.key]} onChange={(v) => setValue(f.key, v)} />
            </div>
          );
        })}
      </div>

      {/* Actions */}
      <div className="flex gap-2 pt-2">
        <button className="px-3 py-2 bg-gray-100 rounded" onClick={onPrev}>Back</button>
        <button className="px-3 py-2 bg-gray-100 rounded" onClick={onPrice}>Price preview</button>
        {step?.submit ? (
          <button className="px-3 py-2 bg-black text-white rounded" onClick={onSubmit}>Submit</button>
        ) : (
          <button className="px-3 py-2 bg-blue-600 text-white rounded" onClick={onNext}>Next</button>
        )}
      </div>
    </div>
  );
}

function isVisible(field, answers) {
  if (!field.visibleIf) return true;
  try {
    if (field.visibleIf.startsWith('answers.')) {
      const path = field.visibleIf.replace('answers.', '');
      return !!getPath(answers, path);
    }
  } catch (e) {}
  return true;
}

function getPath(obj, path) {
  return path.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : undefined), obj);
}
