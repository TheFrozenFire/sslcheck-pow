SSL Configuration Auditing Proof of Work Extension
==================================================

In this repository is the preliminary braindump of my proposal for a TLS
extension which provides the facility for TLS clients to request that the
server return proof of work demonstrating their due diligence in performing
regular audits of their TLS configuration.

If you have suggestions on how to improve/refine/simplify this proposal, please
feel free to create issues in this repository's issue tracker. While this is
not yet a formal RFC by any means, feedback is appreciated.

Proposed Handshake Flow
-----------------------

### Server Executes Audits

As a scheduled task, the server executes requests to third-party TLS/SSL
scan services (e.g. [SSLLabs](https://www.ssllabs.com/ssltest/)). In the
response that these services provide, they would include an "audit payload" in
a standardized format.

This payload would include required information:

* The scanned hostname and port
* The time that the scan completed
* The version of their scan engine
* The type/intensity of their scan (perhaps on a scale to indicate how thorough
  the scan was)
* A score on the scale of 0-100, defined by their own metrics

The payload could also include additions (akin to TLS extensions themselves)
such as the IP addresses that were audited, certificate hostnames that were
valid, and perhaps some sort of fingerprint to be used later to determine
whether the scanned SSL configuration matches what the client eventually
connects to.

In addition to this payload, they would include a signature for the payload,
which the client can use to verify that the audit results were not
forged/faked or tampered with by an attacker.

This payload and signature would be saved for each service, and fed into
the server's TLS configuration.

### Client Performs Handshake

As part of the client's ClientHello phase of the TLS handshake, an extension
would be included indicating that the client requests audit proof.

### Server Performs Handshake

As part of the server's ServerHello phase of the TLS handshake, it determines
what audit payloads to return according to whatever trust model is adopted
(see below). If no audit payloads are supported by the client, an empty
response is returned.

### Client Verifies Handshake

Upon receiving the ServerHello response, the client inspects the resulting list
of audit payloads.

Each payload is verified against known public keys. Each payload is verified
according to the client's preferred strictness.

Example verifications would be as follows (but none are required):

* The scanned hostname and port match the requested hostname
* The time that the scan was completed was no more than `x` days ago
* The version of the scan engine is known to be recent
* The intensity of the scan was sufficient
* The score provided is above a given threshold
* The IP address to which the client is connected is in the returned list
* The certificate hostnames match what is known
* The host SSL configuration has not since changed

Based on these factors, the client can then take preferred action.

Examples of such action would be:

* Display a warning message to the user
* Display a positive indicator to the user
* Prompt the user to continue
* Abort the connection entirely (particularly in an automated context)

Trust Model
-----------

A few different trust models might be considered. Each has benefits and
detriments.

* The data portion of this extension could consist of a list of hashed public
  keys for the services that the client trusts to perform scans of services.  
  The issue with this is that it effectively creates a requirement for a novel
  trust store, duplicating the purpose of the trust chain that already exists
  for certificates.  
  However, this increases the choice that server operators would have in
  selecting which auditors they support, as well as the client's choice in which
  auditors they trust.
* The issuing CA for the server's host certificate could also sign certificates
  for the auditors which includes an
  [extended key usage](https://en.wikipedia.org/wiki/X.509#Extensions_informing_a_specific_usage_of_a_certificate).  
  The issue here is that not all issuing CAs will take the time to do this
  signing, and not all auditors will bother to request it of all issuing CAs.
  This could, however, be mitigated by clients simply including an auditor
  certificate bundle on their system.  
  The benefit would be that a bad actor would have less of a chance to
  inject a compromised auditor into the trust chain.
* Any CA could sign any auditor for the extended key usage.  
  The detriment of this is fairly obvious. Just as bad certificate issuers
  sometimes get into the trust chain, so could bad auditors.  
  The benefit, however, would be ubiquitous support for more auditors, providing
  some semblance of choice.

A common issue for the latter two choices would be that the server would have
no indication as to what the client prefers. This would mean that the server
would have to simply include results from any or all auditors in order to
guarantee that at least some results would be supported by the client.

Common Questions
----------------

* Q: Doesn't TLS already automatically do what these scans do?  
  A: To some extent, yes. TLS can usually be configured to restrict what
     cipher suites it supports. It also has existing extensions for doing things
     like OCSP stapling.  
     However, for other vulnerability factors like downgrade and timing attacks,
     it is too resource intensive for the client to perform these checks itself.
     Any additional negotiation reduces SSL performance. By delegating this
     auditing to the server and requiring proof of that work, we can limit the
     additional negotiation.  
     Additionally, so far as I know, TLS has no built-in facility or extensions
     for preventing zero-day attacks. As we've seen with recent attacks like
     POODLE, there is significant lag-time between our ability to detect
     vulnerability to these attacks, and the patches that resolve the issues.  
     By allowing servers to "staple" their audit results to requests, they can
     confirm that third-party authorities have verified that they are not
     vulnerable to known attacks.

* Q: Can't these results be forged/tampered with?  
  A: Maybe. A service could conceivably redirect scanning services to a separate
     server with a "good" configuration, while their actual client-facing
     servers are vulnerable.  
     However, there isn't really any substantial benefit to doing this. Service
     providers could instead direct that time spent to actually fixing their
     configurations.  
     Additionally, as indicated in the proposed payload, a service could
     somehow provide a "fingerprint" of the SSL configuration that the host
     they scanned was using. If the client can verify this fingerprint, they
     can detect any such forgery.

* Q: Are the scan services willing to take on this additional load?  
  A: Again, maybe. If every server in the world that uses SSL started hammering
     them with requests every day, today, they might put a stop to it. But,
     as with all sorts of demand, supply will increase to meet it.  
     I would foresee paid services offering audit prioritization cropping up,
     and there may be an arms race as we saw with anti-virus software to offer
     more reliable detection of new vulnerabilities, earlier-on.  
     One would hope that free services would also exist in some capacity, but
     I would admit outright that there is the potential for this to become the
     same sort of mess as exists with SSL certificates. Clients might not
     accept scans from those "free" audit services, because of perception.  
     I believe, however, that the gains well outweigh the risk, in that respect.

* Q: What happens if the server doesn't support it (or has no audit provider
     overlap)?  
  A: The same thing that happens if the scans fail, presumably. It is indeed
     a limitation of this proposal for now, but I think that until support
     for this functionality was widespread, all clients would simply treat
     positive results as a positive indicator, as opposed to treating no results
     as a reason to abort.

Open Considerations
-------------------
