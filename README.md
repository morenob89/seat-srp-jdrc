# seat-srp-jdrc
A module for SeAT that tracks SRP requests

## THIS IS A FORK OF ORIGINAL SEAT-SRP MADE FOR JDRC CORP

> **Note:** This project was originally [eveseat-plugins/seat-srp](https://github.com/eveseat-plugins/seat-srp) (published as `cryptatech/seat-srp`). It has since been forked, modified, and is now maintained by **morenob89** at [morenob89/seat-srp-jdrc](https://github.com/morenob89/seat-srp-jdrc) for use by **JDRC** only. Full credit for the original work goes to the upstream authors.

This plugin, written for [SeAT](https://github.com/eveseat/seat), provides your instance with a way to manage your ship replacement program (SRP).

[![License](https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square)](https://raw.githubusercontent.com/morenob89/seat-srp-jdrc/master/LICENSE)

## Quick Installation:

Please see the SeAT docs for installation instructions [HERE](https://eveseat.github.io/docs/community_packages/).

The composer string to use is `morenob89/seat-srp-jdrc`

And now, when you log into SeAT, you should see a 'Ship Replacement Program' link on the left.

## Price Provider Setup

For the **Simple** and **Advanced** payout modes (which price losses from market value), you must have configured at least one PriceProvider. See [here](https://github.com/recursivetree/seat-prices-core) for available providers. The **Flat / Matrix** mode (see below) pays from a fixed table and does **not** require a price provider.

## SRP Payout Calculations

### Simple SRP

By default, the application is configured in simple mode. In this mode, the SRP payout is calculated by using the for the whole killmail.

### Advanced SRP

Advanced SRP can be enabled in the settings menu. Once enabled, the SRP Admin will need to specify rules around payout calculations. The rule types available are `Type`, `Group` and `Default`. The rules are matched in that order with the first match being used to calculate payout value.

#### Shared Configuration Options

- **Price Source** - Where the pricing of individual elements will be drawn from
- **Base Value** - A fixed ISK amount added to each payout from this rule
- **Hull %** - The percentage of the ship hull value to be paid out. 
- **Fit %** - The percentage of the ship fit value to be paid out. 
- **Cargo %** - The percentage of the ship cargo value to be paid out. 
- **Deduct Insurance** - If selected, the payout will be reduced by the benefit gained from insurance (payout - cost)

#### Rule Types

##### Type Rules
Type rules match the ship type exactly, for example a Scorpion or Blackbird. Note that variants are considered separate ships. Ie a Raven is different to a Raven Navy Issue. 

##### Group Rules
Group rules match based on the group of the ship, such as `Frigate`, `Shuttle` or `Battleship`.

##### Default Rule
The default rule is the rule used when there are no type or group rules that have been triggered. The default rule is a catch all for any remaining payout calculations.

### Flat / Matrix SRP (JDRC)

Flat / Matrix mode pays a **fixed ISK amount** from a payout table defined in code, instead of pricing the killmail from market value. Enable it under **Settings → SRP Method → Flat / Matrix**. No price provider is required in this mode.

The payout table lives in **`src/Config/srp.payouts.php`** — edit that file to change the corp's payouts. Each row combines a *meta tier* (T1, Logistics, T2/Faction, T3, Entosis, Pod) with a *hull type*, and holds two payout columns.

When requesting, the pilot picks an **Operation Type**:

- **Peacetime** → reads the *Peacetime* column.
- **Strategic** → reads the *Strategic / FOB / Corp Op* column.

The **ship class** (table row) is auto-detected from the killmail's hull, but the pilot can correct it — this is needed for cases that can't be detected from the hull alone: **T1 logistics** (same hull group as combat T1), **Entosis** (a fitted module, not a hull), and **Pod / implants**. Anything the classifier can't match falls back to an *Unclassified* row that pays 0 ISK and is decided manually.

A payout of `0` (Entosis peacetime, Pod, Unclassified) means "no default — decide manually". **SRP admins can override the ISK amount** for any request on the Approval page.

## Discord Webhook (optional)

Automated notifications of new SRP Requests submitted in Discord

***In Discord application:***

1. On a channel of your choice, click the cog icon to open the channel settings
2. In the channel settings, navigate to the Webhooks tab
3. Click `Create Webhook`
4. Fill in name for the webhook and (optional) image
5. Copy the Webhook URL
6. Click `Save` to finish creating the webhook

***In SeAT file:***

The Ship Replacement Program Settings page accepts two variables for the webhook:

1. (required) `Webhook URL`: this is the url you copied when creating the webhook in Discord
2. (optional) `Discord Mention Role`: this can be a room mention (e.g. `@here`), a Discord role ID, or a specific user ID
        - Role ID and User ID can be obtained by typing `/@rolename` into a channel (e.g. `/@srp_manager`) 


Example of entries:

```
Webhook URL = https://discordapp.com/api/webhooks/513619798362554369/Px9VQwiE5lhhBqOjW7rFBuLmLzMimwcklC2kIDJhQ9hLcDzCRPCkbI0LgWq6YwIbFtuk
Discord Mention Role = <@&198725153385873409>
```


Good luck, and Happy Hunting!!  o7


## Usage Tracking

In order to get an idea of the usage of this plugin, a very simplistic form of anonymous usage tracking has been implemented.

Read more about the system in use [here](https://github.com/Crypta-Eve/snoopy)
