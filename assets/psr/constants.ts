export const RAG = { gray:0, green:1, amber:2, red:3 } as const;
export const RAG_LABEL = ['Gray','Green','Amber','Red'] as const;
export const WEATHER_LABEL = ['?','Stormy','Rainy','Cloudy','Clear','Sunny'] as const;
export const WEATHER_ICON: Record<number,string> = {1:'🌩️',2:'🌧️',3:'☁️',4:'🌤️',5:'☀️'};
