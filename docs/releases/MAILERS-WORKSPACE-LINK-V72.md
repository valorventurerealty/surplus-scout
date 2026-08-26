# V72 — Mailers workspace link

The authenticated workspace navigation includes a **Mailers** item beside Email. It opens VVR's Stannp application in a new browser tab at `https://app-us1.stannp.com/`.

The destination is configured through `config/vvr.php` and may be overridden privately without changing application code:

```dotenv
VVR_MAILERS_URL="https://app-us1.stannp.com/"
```

The navigation renders the shortcut only when the resolved destination is a valid HTTPS URL. It uses `target="_blank"` and `rel="noopener noreferrer"` so the external application cannot control the VVR browser tab. No Stannp credentials or API keys are stored by this shortcut.
