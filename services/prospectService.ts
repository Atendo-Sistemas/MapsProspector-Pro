import axios from "axios";

export async function getProspects(query: string, location: string) {
  const res = await axios.get("http://localhost:4000/api/prospect", {
    params: { query, location },
  });
  return res.data;
}
