# Property status business-order sorting

Property status sorting now follows the operating pipeline instead of alphabetical database order:

1. Research
2. Bidding
3. Owned
4. Actively Working
5. Marketing
6. Under Contract
7. Sold
8. Archived

Ascending sorting uses this sequence and descending sorting reverses it. The shared model scope uses a parameter-bound SQL `CASE` expression compatible with MySQL and the SQLite test environment.
