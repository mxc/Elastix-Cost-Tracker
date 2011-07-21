  +----------------------------------------------------------------------+
  | Copyright (c) 2011 Jumping Bean                                      |
  +----------------------------------------------------------------------+
  | Unit 3 Appian Place, 373 Kent Avenue, Ferndale, South Africa         |
  | Tel:011 781 8014                                                     |
  | http://www.jumpingbean.co.za     http://www.ip-pbx.co.za             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Original Code is: Elastix Open Source.                           |
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  |                                                                      |
  | Translate by: Bruno Macias                                           |
  | Email: bmacias@palosanto.com                                         |
  +----------------------------------------------------------------------+

Cost Tracker
============

This module is designed to be a simple cost tracking application for offices
that use pinsets to manage access to trunks. It is the first beta release. I am
sure there are quite a few bugs. We use a much simpler version at some of our
clients but recognised the need to improve the module quiet a bit as some more
complex scenarios are beginning to arise.

Challenges
----------
Although this kind of module shouldn't be that complex some difficulties arise
because:

1) CDR records only keep the accountcode(pin) used and do not record the pinset
to which it applies. Since there can be duplicate pins across pinsets this means
it is possible for two user to have the same pin but they are using different
pin sets. When reporting we need to be able to distinguish.

2) For reporting we need to keep history of when a pin was in use and by who.
Since asterisk only bothers about current active pins it means a pin can be
reused over time but we need a way to know who was using a particular pin at
a point in time. We therefore keep history. We map elastix users to pins and we
need to track changes to user passwords/pins to report accurately.

3)Given that most people will have historical data the need to accommodate
matching users and  pins that are no longer valid means checking constraints.
i.e. we want to ensure that a user cannot have two pins allocated to them at
a particular point in time for a particular pinset. Also we do not delete pins
but merely mark them as inactive so they can still be used in historical
reports.

4) Since pins are managed in freepbx we make the cost tracker module write
active pins to the freepbx database and then mark freepbx as needing to be
updated. This is currently a manual step. i.e you will need to go to FreePBX
and click "Apply Changes"

5) Because the CDR records are stored in mysql we had to use mysql for the
database for cost tracker.

TO DO:
------

1) Move menu to a more appropriate place, not sure about how the module.xml file
works for this

2) Add ability of users to login and change passwords

3) Add ability of users to login and check usage
