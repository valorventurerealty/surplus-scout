# Sortable CRM tables update

Properties, Deals, Contacts, and Tasks now use server-side sortable headers. Clicking an inactive header sorts ascending; clicking the active header toggles between ascending and descending. Search and filter parameters remain attached to sort links and pagination.

## Properties

Property, Parcel / county, Owner, Type, Acreage, All-in / investor, Expected sale / profit, and Status are sortable. Financial sort keys are rejected for users who cannot view property financials.

## Deals

Deal, Property, Primary contact, Assigned, Close date, Contract / projected, and Status are sortable. Contract / projected remains permission-bound.

## Contacts

Name, Company, Email, Associated tasks, and Next follow-up are sortable. Associated tasks uses the existing open-task aggregate.

## Tasks

Task, Associated record, Assigned to, Due, Priority, and Status are sortable. Associated records are grouped by record type and sorted by the displayed contact name, property address, or deal title. Priority follows the business sequence Low, Normal, High, Urgent. The table now also displays linked deals correctly instead of labeling them standalone.

## Security and stability

The controllers validate `sort` and `direction` against fixed allowlists. Client input is never used as an SQL identifier. Related-record sorting uses constrained Eloquent subqueries, aggregate sorting uses a server-generated alias, and every query adds an ID tie-breaker for stable pagination.
