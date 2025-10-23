<?php
namespace App\Entity;

enum QwResponseStatus: string { case InProgress='in_progress'; case Submitted='submitted'; case Approved='approved'; case Rejected='rejected'; }
