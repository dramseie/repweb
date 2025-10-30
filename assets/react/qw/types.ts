// assets/react/qw/types.ts

// QW (single entry) — shared types
export type Id = number;
export type ItemType = 'header' | 'question';
export type UiType =
  | 'input' | 'textarea' | 'wysiwyg'
  | 'select' | 'multiselect' | 'radio' | 'checkbox'
  | 'slider' | 'color' | 'date' | 'time' | 'daterange' | 'integer'
  | 'autocomplete' | 'chainselect'
  | 'image' | 'file' | 'voice' | 'video' | 'toggle';

export interface ItemDTO {
  id: Id;
  parentId: Id | null;
  type: ItemType;
  title: string;
  help?: string | null;
  sort: number;
  outline?: string | null;
  required?: boolean;
  visibleWhen?: any;
}

export interface QuestionnaireDTO {
  id: Id;
  title: string;
  status: string;
  items: ItemDTO[];
}

export interface FieldDTO {
  id: Id;
  ui_type: UiType;
  placeholder?: string | null;
  default_value?: string | null;
  options_json?: any[] | null;
}

export interface RuntimeItem {
  id: Id;
  parentId: Id | null;
  type: ItemType;
  title: string;
  help?: string | null;
  outline?: string | null;
  required?: boolean;
  visibleWhen?: any;
  sort?: number;
}

export interface RuntimeField {
  id: Id;
  itemId: Id;
  uiType: UiType;
  label?: string | null;
  placeholder?: string | null;
  defaultValue?: string | null;
  minValue?: number | null;
  maxValue?: number | null;
  stepValue?: number | null;
  options?: Array<string | { label?: string | null; value?: string | number | boolean | null } | null> | null;
  help?: string | null;
}

export interface RuntimeAnswer {
  itemId: Id;
  fieldId: Id | null;
  valueText?: string | null;
  valueJson?: any;
}

export interface RuntimeResponse {
  id: Id;
  status: 'in_progress' | 'submitted' | string;
  startedAt?: string | null;
  submittedAt?: string | null;
  answers: RuntimeAnswer[];
}

export interface RuntimePayload {
  ci: {
    key: string;
    name: string;
    tenantId: number;
    type: string;
    application: null | {
      id: Id | null;
      appCi: string;
      appName: string;
      environment: string | null;
      status: string;
    };
  };
  questionnaire: {
    id: Id;
    title: string;
    description?: string | null;
  };
  items: RuntimeItem[];
  fields: RuntimeField[];
  response: RuntimeResponse;
}
