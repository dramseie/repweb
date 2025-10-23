<?php
namespace App\Entity;

enum QwWorkflowAction: string { case Submit='submit'; case RequestChanges='request_changes'; case Approve='approve'; case Reject='reject'; case Reopen='reopen'; case Archive='archive'; }
