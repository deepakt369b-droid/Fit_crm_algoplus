# Credits

## WhatsApp marketing module

FitCRM's WhatsApp marketing features (shared inbox, contacts, templates,
broadcasts, automations — built incrementally across several milestones)
were scoped using [`ArnasDon/wacrm`](https://github.com/ArnasDon/wacrm) as
the reference specification: its data model, feature set, and Meta Cloud
API integration approach informed the design here.

wacrm is MIT-licensed and explicitly intended to be forked ("Fork it,
brand it, host it"). Because FitCRM's implementation is a from-scratch
reimplementation against a different stack (Laravel/Filament/MySQL,
versus wacrm's Next.js/Supabase/Postgres) rather than a copy of its code,
no wacrm source is reproduced here. This credit is given regardless, both
because it's the honest thing to do and because wacrm's design shaped
real decisions in this module.

```
MIT License

Copyright (c) ArnasDon

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files, to deal in the
software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies, subject to the standard MIT License conditions.
```

See the original repository for the full, authoritative license text.
