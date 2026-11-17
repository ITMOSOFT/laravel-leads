<?php
declare(strict_types=1);
namespace ItmoSoft\Leads\Enums;
enum LeadStatus:string { case New='new'; case Contacted='contacted'; case Qualified='qualified'; case Unqualified='unqualified'; case Converted='converted'; case Lost='lost'; }
