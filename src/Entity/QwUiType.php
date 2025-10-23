<?php
namespace App\Entity;

enum QwUiType: string {
    case Input='input'; case Textarea='textarea'; case Wysiwyg='wysiwyg';
    case Select='select'; case Multiselect='multiselect'; case Radio='radio'; case Checkbox='checkbox';
    case Slider='slider'; case Color='color'; case Date='date'; case Time='time'; case Daterange='daterange'; case Integer='integer';
    case Autocomplete='autocomplete'; case Chainselect='chainselect';
    case Image='image'; case File='file'; case Voice='voice'; case Video='video'; case Toggle='toggle';
}
