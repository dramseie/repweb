// assets/react/services/RocketChatDDP.js
import { DDPSDK } from '@rocket.chat/ddp-client';

class RocketChatDDP {
  constructor() {
    this.client = null;
    this.subs = new Map(); // roomId -> stopper
    this.listeners = new Set(); // cb({roomId, msg})
    this.connected = false;
    this.config = null;
  }

  normalizeBase(url) {
    if (!url) return '';
    return url.replace(/\/websocket$/, '').replace(/^wss:\/\//, 'https://');
  }

  async connect({ url, token }) {
    if (this.connected && this.config?.url === url && this.config?.token === token) return;
    await this.disconnect();

    const base = this.normalizeBase(url);
    this.client = await DDPSDK.createAndConnect(base);
    await this.client.account.loginWithToken(token);
    this.connected = true;
    this.config = { url, token };
  }

  async disconnect() {
    for (const stopper of this.subs.values()) {
      try { stopper?.stop?.(); } catch {}
    }
    this.subs.clear();
    if (this.client) {
      try { this.client.connection?.disconnect?.(); } catch {}
      this.client = null;
    }
    this.connected = false;
  }

  async watchRooms(roomIds = []) {
    if (!this.connected || !this.client) return;
    // remove subscriptions no longer needed
    for (const [rid, stopper] of this.subs.entries()) {
      if (!roomIds.includes(rid)) {
        try { stopper?.stop?.(); } catch {}
        this.subs.delete(rid);
      }
    }
    // add missing
    for (const rid of roomIds) {
      if (this.subs.has(rid)) continue;
      const stopper = this.client.stream('room-messages', rid, (msg) => {
        for (const cb of this.listeners) {
          try { cb({ roomId: rid, msg }); } catch {}
        }
      });
      this.subs.set(rid, stopper);
    }
  }

  onMessage(cb) {
    this.listeners.add(cb);
    return () => this.listeners.delete(cb);
  }
}

export const RC_DDP = new RocketChatDDP();
