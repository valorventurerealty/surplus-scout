# Pipeline workspace update

This update adds Under Contract between Marketing and Sold and activates Pipeline as a first-class navigation workspace.

Pipeline uses property status directly. Stage changes are authorized and validated on the server, executed transactionally, and captured by the existing property audit system. The workspace includes filters, stage totals, direct property links, permission-controlled financial visibility, and a responsive board.

The update has no new database migration. It includes feature tests for stage ordering, status movement, policy enforcement, filters, invalid status rejection, audit logging, and financial privacy.
