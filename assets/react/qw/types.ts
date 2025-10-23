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
